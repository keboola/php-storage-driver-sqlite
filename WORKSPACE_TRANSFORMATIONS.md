# Workspace Transformations with SQLite Driver

This document explains how workspace transformations work with the SQLite storage driver, particularly in multi-pod Kubernetes environments with cloud storage.

## Overview

Workspaces in Keboola Connection are isolated environments where transformations can run for extended periods (hours) without impacting production data. The SQLite driver implements workspaces as **separate SQLite database files**, enabling true isolation.

## Architecture

### Database Structure

- **Production Database**: `{projectId}/{bucketName}.db` - Contains production tables
- **Workspace Database**: `{projectId}/workspace-{workspaceId}.db` - Isolated transformation environment

Each workspace gets its own SQLite database file, completely separate from production databases.

### Cloud Storage Integration

When using S3 cloud storage:
- Database files are stored in S3 at: `s3://{bucket}/{storageRoot}/{projectId}/{databaseName}.db`
- Files are automatically downloaded to local temp storage when accessed
- Files are automatically uploaded back to S3 after operations complete
- Distributed locks prevent concurrent modifications across pods

## Transformation Workflow

### Phase 1: Load Data into Workspace

**Duration**: Minutes (depends on data size)

1. **Create Workspace**
   ```php
   // Creates workspace-{workspaceId}.db
   CreateWorkspaceCommand
   ```

2. **Load Tables from Production**
   
   The Connection application handles data loading by:
   - Downloading production database from S3 (read-only snapshot)
   - Downloading workspace database from S3
   - Copying table data from production to workspace using one of these methods:
   
   **Method A: ATTACH DATABASE (Recommended)**
   ```sql
   ATTACH DATABASE '/path/to/production.db' AS prod;
   CREATE TABLE workspace_table AS SELECT * FROM prod.production_table;
   DETACH DATABASE prod;
   ```
   
   **Method B: Export/Import via CSV**
   ```php
   // Export from production
   TableExportToFileCommand -> CSV file
   // Import to workspace
   TableImportFromFileCommand <- CSV file
   ```

3. **Upload Workspace Database**
   - Workspace database is uploaded to S3
   - Production database lock is released
   - **Production is now free for other operations**

### Phase 2: Run Transformation

**Duration**: Hours (long-running transformations)

- Transformation runs entirely on workspace database
- **No production database access required**
- **No distributed locks held**
- **No impact on production operations**
- Other pods can freely access production databases

### Phase 3: Unload Data from Workspace

**Duration**: Minutes (depends on data size)

1. **Download Latest Databases**
   - Download current production database from S3
   - Download workspace database from S3
   - Acquire distributed lock on production database

2. **Apply Changes to Production**
   
   **Method A: ATTACH DATABASE (Recommended)**
   ```sql
   ATTACH DATABASE '/path/to/workspace.db' AS ws;
   
   -- Option 1: Replace entire table
   DROP TABLE IF EXISTS production_table;
   CREATE TABLE production_table AS SELECT * FROM ws.workspace_table;
   
   -- Option 2: Merge changes (if tracking changes)
   INSERT OR REPLACE INTO production_table 
   SELECT * FROM ws.workspace_table;
   
   DETACH DATABASE ws;
   ```
   
   **Method B: Export/Import via CSV**
   ```php
   // Export from workspace
   TableExportToFileCommand -> CSV file
   // Import to production (replace or append)
   TableImportFromFileCommand <- CSV file
   ```

3. **Upload Production Database**
   - Production database is uploaded to S3
   - Distributed lock is released
   - Workspace database can be dropped

4. **Drop Workspace**
   ```php
   DropWorkspaceCommand
   ```

## Key Advantages

### 1. True Isolation
- Workspace transformations cannot corrupt production data
- Transformation failures don't affect production
- Multiple workspaces can run simultaneously

### 2. No Long-Running Locks
- Production database is only locked during load/unload (minutes)
- Transformation phase (hours) requires no locks
- Other operations can proceed during transformations

### 3. Multi-Pod Safety
- Distributed locking prevents concurrent modifications
- Each pod can work with local copies of databases
- S3 sync ensures consistency across pods

### 4. Efficient for Long Transformations
- Transformation runs on local SQLite (fast)
- No network overhead during transformation
- Only sync at beginning and end

## Implementation Example

### Helper Methods in SqliteConnectionManager

```php
class SqliteConnectionManager
{
    /**
     * Copy table from one database to another using ATTACH
     */
    public function copyTable(
        string $sourceProjectId,
        string $sourceDatabaseName,
        string $sourceTableName,
        string $targetProjectId,
        string $targetDatabaseName,
        string $targetTableName,
        bool $replaceIfExists = true
    ): void {
        $sourceDb = $this->getLocalDatabasePath($sourceProjectId, $sourceDatabaseName);
        $targetConnection = $this->getConnection($targetProjectId, $targetDatabaseName);
        
        $targetConnection->exec(sprintf('ATTACH DATABASE "%s" AS source', $sourceDb));
        
        if ($replaceIfExists) {
            $targetConnection->exec(sprintf('DROP TABLE IF EXISTS "%s"', $targetTableName));
        }
        
        $targetConnection->exec(sprintf(
            'CREATE TABLE "%s" AS SELECT * FROM source."%s"',
            $targetTableName,
            $sourceTableName
        ));
        
        $targetConnection->exec('DETACH DATABASE source');
        
        $this->syncDatabaseToCloud($targetProjectId, $targetDatabaseName);
    }
}
```

### Usage in Connection Application

```php
// Phase 1: Load data into workspace
$workspaceId = 'ws-123';
$projectId = 'project-456';

// Create workspace
$driver->runCommand($credentials, new CreateWorkspaceCommand([
    'workspaceId' => $workspaceId,
    'projectId' => $projectId,
]));

// Load tables (Connection app handles this)
$connectionManager = $driver->getConnectionManager();
$connectionManager->copyTable(
    sourceProjectId: $projectId,
    sourceDatabaseName: 'in.c-main',
    sourceTableName: 'customers',
    targetProjectId: $projectId,
    targetDatabaseName: 'workspace-' . $workspaceId,
    targetTableName: 'customers'
);

// Phase 2: Run transformation (hours)
// Transformation runs on workspace database
// No production access needed

// Phase 3: Unload data from workspace
$connectionManager->copyTable(
    sourceProjectId: $projectId,
    sourceDatabaseName: 'workspace-' . $workspaceId,
    sourceTableName: 'customers_transformed',
    targetProjectId: $projectId,
    targetDatabaseName: 'out.c-results',
    targetTableName: 'customers_transformed',
    replaceIfExists: true
);

// Drop workspace
$driver->runCommand($credentials, new DropWorkspaceCommand([
    'workspaceObjectName' => 'workspace-' . $workspaceId,
]));
```

## Performance Considerations

### Load/Unload Performance
- **Small tables** (<1M rows): Seconds
- **Medium tables** (1M-10M rows): Minutes
- **Large tables** (>10M rows): Consider chunking or incremental loads

### Transformation Performance
- SQLite is fast for local operations
- No network latency during transformation
- WAL mode enables concurrent reads

### S3 Sync Performance
- Download/upload time depends on database size
- Typical: 100MB database = ~5-10 seconds
- Use compression for large databases

## Limitations and Workarounds

### 1. Cross-Database Queries
**Limitation**: SQLite doesn't support cross-database queries natively

**Workaround**: Use ATTACH DATABASE for temporary cross-database access
```sql
ATTACH DATABASE '/path/to/other.db' AS other;
SELECT * FROM other.table_name;
DETACH DATABASE other;
```

### 2. Concurrent Writes
**Limitation**: SQLite has limited concurrent write support

**Workaround**: 
- Use distributed locking for write operations
- WAL mode enables concurrent reads
- Workspaces are isolated, no concurrent writes to same database

### 3. Database Size
**Limitation**: Large databases increase S3 sync time

**Workaround**:
- Use incremental loads when possible
- Consider table partitioning
- Compress databases before upload

## Monitoring and Debugging

### Check Database Location
```php
$path = $connectionManager->getLocalDatabasePath($projectId, $databaseName);
echo "Local path: $path\n";

$cloudKey = $connectionManager->getCloudKey($projectId, $databaseName);
echo "S3 key: $cloudKey\n";
```

### Verify Sync Status
```php
// Check if database exists in cloud
$exists = $connectionManager->databaseExists($projectId, $databaseName);

// Force sync to cloud
$connectionManager->syncDatabaseToCloud($projectId, $databaseName);
```

### Lock Debugging
Distributed locks are stored in S3 at: `s3://{bucket}/locks/{projectId}-{databaseName}.lock`

Lock file contains:
```json
{
  "lock_id": "lock_abc123",
  "acquired_at": 1234567890,
  "expires_at": 1234567890,
  "pid": 12345,
  "hostname": "pod-xyz"
}
```

## Best Practices

1. **Always use distributed locks** for production database modifications
2. **Keep load/unload phases short** - only copy necessary data
3. **Use ATTACH DATABASE** for efficient table copying
4. **Monitor S3 sync times** - optimize database sizes if needed
5. **Clean up workspaces** after transformations complete
6. **Use workspace isolation** - never modify production during transformation
7. **Test with small datasets** before running large transformations

## Troubleshooting

### Problem: Lock timeout
**Solution**: Increase lock timeout or check for stale locks in S3

### Problem: Slow S3 sync
**Solution**: Reduce database size, use compression, or optimize S3 configuration

### Problem: Workspace data not visible
**Solution**: Ensure workspace database was synced to S3 after load phase

### Problem: Production data not updated
**Solution**: Verify unload phase completed and synced to S3

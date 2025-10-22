# Keboola SQLite Storage Driver

PHP implementation of the Keboola Storage Driver for SQLite databases. This driver is production-ready and designed for use in Kubernetes environments with cloud storage backends.

## Installation

```bash
composer require keboola/storage-driver-sqlite
```

## Requirements

- PHP 8.2 or higher
- PDO SQLite extension
- SQLite3 extension
- AWS SDK (for S3 storage)

## Features

- Full table operations (create, drop, alter, import, export)
- Workspace support with isolated databases
- Cloud storage backend (S3-compatible)
- Distributed locking for multi-pod deployments
- Automatic sync between local and cloud storage
- WAL mode for better concurrency
- Transaction support for data integrity

## Usage

### Local Development (Filesystem Storage)

For local development and testing, you can use the driver without cloud storage:

```php
use Keboola\StorageDriver\Sqlite\SqliteDriverClient;

$client = new SqliteDriverClient('/path/to/local/storage');
```

### Production (S3 Cloud Storage)

For production deployments in Kubernetes, use S3-compatible cloud storage:

```php
use Keboola\StorageDriver\Sqlite\SqliteDriverClient;

$client = SqliteDriverClient::createWithS3Storage(
    s3Bucket: 'my-sqlite-databases',
    s3Region: 'us-east-1',
    s3Endpoint: null, // Optional: for S3-compatible services
    storageRoot: 'sqlite-databases', // S3 key prefix
    internalLogger: $logger // Optional PSR-3 logger
);
```

### How Cloud Storage Works

When cloud storage is enabled:

1. **Download on Access**: SQLite database files are automatically downloaded from S3 to local temp storage when accessed
2. **Distributed Locking**: Operations acquire distributed locks using S3 to prevent concurrent modifications
3. **Upload on Completion**: Database files are automatically uploaded back to S3 after operations complete
4. **WAL Files**: SQLite WAL (Write-Ahead Log) and SHM (Shared Memory) files are also synced

This architecture allows multiple Kubernetes pods to safely access the same SQLite databases without conflicts.

### Manual Sync

If you need to manually sync a database to cloud storage:

```php
$connectionManager = $client->getConnectionManager();
$connectionManager->syncDatabaseToCloud($projectId, $databaseName);
```

### Using Distributed Locks

For custom operations that need locking:

```php
$connectionManager = $client->getConnectionManager();

$result = $connectionManager->withLockedDatabase($projectId, $databaseName, function($connection) {
    // Your database operations here
    $connection->exec('INSERT INTO ...');
    return $someResult;
});
```

### Workspace Transformations

For detailed information about how workspace transformations work with long-running operations in Kubernetes environments, see [WORKSPACE_TRANSFORMATIONS.md](WORKSPACE_TRANSFORMATIONS.md).

Key features:
- Isolated workspace databases for transformations
- No production locks during transformation execution
- Efficient data loading/unloading using ATTACH DATABASE
- Multi-pod safe with distributed locking

## Development

### Running Tests

```bash
composer tests-unit
composer tests-functional
composer tests
```

### Code Quality

```bash
composer phpcs
composer phpstan
composer check
```

## License

MIT

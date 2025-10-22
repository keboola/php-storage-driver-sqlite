<?php

declare(strict_types=1);

namespace Keboola\StorageDriver\Sqlite;

use Keboola\StorageDriver\Sqlite\Storage\CloudStorageInterface;
use Keboola\StorageDriver\Sqlite\Storage\DistributedLock;
use PDO;
use PDOException;
use RuntimeException;

class SqliteConnectionManager
{
    private string $storageRoot;
    
    private array $connections = [];
    
    private ?CloudStorageInterface $cloudStorage;
    
    private ?DistributedLock $distributedLock;
    
    private string $localTempDir;

    public function __construct(
        string $storageRoot,
        ?CloudStorageInterface $cloudStorage = null,
        ?DistributedLock $distributedLock = null
    ) {
        $this->storageRoot = rtrim($storageRoot, '/');
        $this->cloudStorage = $cloudStorage;
        $this->distributedLock = $distributedLock;
        $this->localTempDir = sys_get_temp_dir() . '/sqlite-driver';
        
        if (!is_dir($this->localTempDir)) {
            mkdir($this->localTempDir, 0755, true);
        }
    }

    public function getConnection(string $projectId, string $databaseName = 'main'): PDO
    {
        $key = $projectId . '/' . $databaseName;
        
        if (isset($this->connections[$key])) {
            return $this->connections[$key];
        }

        $dbPath = $this->getLocalDatabasePath($projectId, $databaseName);
        $dbDir = dirname($dbPath);
        
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }

        if ($this->cloudStorage !== null) {
            $this->syncFromCloud($projectId, $databaseName);
        }

        try {
            $pdo = new PDO('sqlite:' . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA journal_mode = WAL');
            
            $this->connections[$key] = $pdo;
            
            return $pdo;
        } catch (PDOException $e) {
            throw new RuntimeException(
                sprintf('Failed to connect to SQLite database at %s: %s', $dbPath, $e->getMessage()),
                0,
                $e
            );
        }
    }

    public function getDatabasePath(string $projectId, string $databaseName = 'main'): string
    {
        return sprintf('%s/%s/%s.db', $this->storageRoot, $projectId, $databaseName);
    }

    private function getLocalDatabasePath(string $projectId, string $databaseName = 'main'): string
    {
        if ($this->cloudStorage === null) {
            return $this->getDatabasePath($projectId, $databaseName);
        }
        
        return sprintf('%s/%s/%s.db', $this->localTempDir, $projectId, $databaseName);
    }

    public function databaseExists(string $projectId, string $databaseName = 'main'): bool
    {
        if ($this->cloudStorage !== null) {
            $cloudKey = $this->getCloudKey($projectId, $databaseName);
            return $this->cloudStorage->fileExists($cloudKey);
        }
        
        return file_exists($this->getDatabasePath($projectId, $databaseName));
    }

    private function getCloudKey(string $projectId, string $databaseName): string
    {
        return sprintf('%s/%s/%s.db', $this->storageRoot, $projectId, $databaseName);
    }

    private function syncFromCloud(string $projectId, string $databaseName): void
    {
        if ($this->cloudStorage === null) {
            return;
        }

        $cloudKey = $this->getCloudKey($projectId, $databaseName);
        $localPath = $this->getLocalDatabasePath($projectId, $databaseName);

        if ($this->cloudStorage->fileExists($cloudKey)) {
            $this->cloudStorage->downloadFile($cloudKey, $localPath);
            
            $walKey = $cloudKey . '-wal';
            $walPath = $localPath . '-wal';
            if ($this->cloudStorage->fileExists($walKey)) {
                $this->cloudStorage->downloadFile($walKey, $walPath);
            }
            
            $shmKey = $cloudKey . '-shm';
            $shmPath = $localPath . '-shm';
            if ($this->cloudStorage->fileExists($shmKey)) {
                $this->cloudStorage->downloadFile($shmKey, $shmPath);
            }
        }
    }

    private function syncToCloud(string $projectId, string $databaseName): void
    {
        if ($this->cloudStorage === null) {
            return;
        }

        $cloudKey = $this->getCloudKey($projectId, $databaseName);
        $localPath = $this->getLocalDatabasePath($projectId, $databaseName);

        if (file_exists($localPath)) {
            $this->cloudStorage->uploadFile($localPath, $cloudKey);
            
            $walPath = $localPath . '-wal';
            if (file_exists($walPath)) {
                $this->cloudStorage->uploadFile($walPath, $cloudKey . '-wal');
            }
            
            $shmPath = $localPath . '-shm';
            if (file_exists($shmPath)) {
                $this->cloudStorage->uploadFile($shmPath, $cloudKey . '-shm');
            }
        }
    }

    public function createDatabase(string $projectId, string $databaseName = 'main'): void
    {
        if ($this->distributedLock !== null) {
            $this->distributedLock->withLock($projectId . '/' . $databaseName, function () use ($projectId, $databaseName) {
                $this->createDatabaseInternal($projectId, $databaseName);
            });
        } else {
            $this->createDatabaseInternal($projectId, $databaseName);
        }
    }

    private function createDatabaseInternal(string $projectId, string $databaseName): void
    {
        $connection = $this->getConnection($projectId, $databaseName);
        
        $connection->exec('
            CREATE TABLE IF NOT EXISTS _keboola_metadata (
                key TEXT PRIMARY KEY,
                value TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->closeConnection($projectId, $databaseName);
        $this->syncToCloud($projectId, $databaseName);
    }

    public function dropDatabase(string $projectId, string $databaseName = 'main'): void
    {
        if ($this->distributedLock !== null) {
            $this->distributedLock->withLock($projectId . '/' . $databaseName, function () use ($projectId, $databaseName) {
                $this->dropDatabaseInternal($projectId, $databaseName);
            });
        } else {
            $this->dropDatabaseInternal($projectId, $databaseName);
        }
    }

    private function dropDatabaseInternal(string $projectId, string $databaseName): void
    {
        $key = $projectId . '/' . $databaseName;
        
        if (isset($this->connections[$key])) {
            unset($this->connections[$key]);
        }

        $localPath = $this->getLocalDatabasePath($projectId, $databaseName);
        
        if (file_exists($localPath)) {
            unlink($localPath);
        }
        
        $walPath = $localPath . '-wal';
        if (file_exists($walPath)) {
            unlink($walPath);
        }
        
        $shmPath = $localPath . '-shm';
        if (file_exists($shmPath)) {
            unlink($shmPath);
        }

        if ($this->cloudStorage !== null) {
            $cloudKey = $this->getCloudKey($projectId, $databaseName);
            
            if ($this->cloudStorage->fileExists($cloudKey)) {
                $this->cloudStorage->deleteFile($cloudKey);
            }
            
            $walKey = $cloudKey . '-wal';
            if ($this->cloudStorage->fileExists($walKey)) {
                $this->cloudStorage->deleteFile($walKey);
            }
            
            $shmKey = $cloudKey . '-shm';
            if ($this->cloudStorage->fileExists($shmKey)) {
                $this->cloudStorage->deleteFile($shmKey);
            }
        }
    }

    public function closeConnection(string $projectId, string $databaseName = 'main'): void
    {
        $key = $projectId . '/' . $databaseName;
        
        if (isset($this->connections[$key])) {
            unset($this->connections[$key]);
        }
    }

    public function closeAllConnections(): void
    {
        $this->connections = [];
    }

    public function syncDatabaseToCloud(string $projectId, string $databaseName = 'main'): void
    {
        $this->closeConnection($projectId, $databaseName);
        $this->syncToCloud($projectId, $databaseName);
    }

    public function withLockedDatabase(string $projectId, string $databaseName, callable $callback)
    {
        if ($this->distributedLock !== null) {
            return $this->distributedLock->withLock($projectId . '/' . $databaseName, function () use ($projectId, $databaseName, $callback) {
                $result = $callback($this->getConnection($projectId, $databaseName));
                $this->syncDatabaseToCloud($projectId, $databaseName);
                return $result;
            });
        }

        $result = $callback($this->getConnection($projectId, $databaseName));
        $this->syncDatabaseToCloud($projectId, $databaseName);
        return $result;
    }

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
        
        if ($this->cloudStorage !== null && !file_exists($sourceDb)) {
            $this->syncFromCloud($sourceProjectId, $sourceDatabaseName);
        }
        
        if (!file_exists($sourceDb)) {
            throw new RuntimeException(
                sprintf('Source database not found: %s/%s', $sourceProjectId, $sourceDatabaseName)
            );
        }

        $targetConnection = $this->getConnection($targetProjectId, $targetDatabaseName);
        
        try {
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
        } catch (\Exception $e) {
            try {
                $targetConnection->exec('DETACH DATABASE source');
            } catch (\Exception $detachException) {
            }
            
            throw new RuntimeException(
                sprintf(
                    'Failed to copy table %s.%s to %s.%s: %s',
                    $sourceDatabaseName,
                    $sourceTableName,
                    $targetDatabaseName,
                    $targetTableName,
                    $e->getMessage()
                ),
                0,
                $e
            );
        }
    }
}

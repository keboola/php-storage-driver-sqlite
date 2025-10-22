<?php

declare(strict_types=1);

namespace Keboola\StorageDriver\Sqlite;

use PDO;
use PDOException;
use RuntimeException;

class SqliteConnectionManager
{
    private string $storageRoot;
    
    private array $connections = [];

    public function __construct(string $storageRoot)
    {
        $this->storageRoot = rtrim($storageRoot, '/');
        
        if (!is_dir($this->storageRoot)) {
            mkdir($this->storageRoot, 0755, true);
        }
    }

    public function getConnection(string $projectId, string $databaseName = 'main'): PDO
    {
        $key = $projectId . '/' . $databaseName;
        
        if (isset($this->connections[$key])) {
            return $this->connections[$key];
        }

        $dbPath = $this->getDatabasePath($projectId, $databaseName);
        $dbDir = dirname($dbPath);
        
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
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

    public function databaseExists(string $projectId, string $databaseName = 'main'): bool
    {
        return file_exists($this->getDatabasePath($projectId, $databaseName));
    }

    public function createDatabase(string $projectId, string $databaseName = 'main'): void
    {
        $connection = $this->getConnection($projectId, $databaseName);
        
        $connection->exec('
            CREATE TABLE IF NOT EXISTS _keboola_metadata (
                key TEXT PRIMARY KEY,
                value TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ');
    }

    public function dropDatabase(string $projectId, string $databaseName = 'main'): void
    {
        $key = $projectId . '/' . $databaseName;
        
        if (isset($this->connections[$key])) {
            unset($this->connections[$key]);
        }

        $dbPath = $this->getDatabasePath($projectId, $databaseName);
        
        if (file_exists($dbPath)) {
            unlink($dbPath);
        }
        
        $walPath = $dbPath . '-wal';
        if (file_exists($walPath)) {
            unlink($walPath);
        }
        
        $shmPath = $dbPath . '-shm';
        if (file_exists($shmPath)) {
            unlink($shmPath);
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
}

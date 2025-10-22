<?php

declare(strict_types=1);

namespace Keboola\StorageDriver\Sqlite\Handler\Table\Import;

use Google\Protobuf\Internal\Message;
use Keboola\StorageDriver\Command\Table\ImportTableFromFileCommand;
use Keboola\StorageDriver\Command\Table\TableImportResponse;
use Keboola\StorageDriver\Credentials\GenericBackendCredentials;
use Keboola\StorageDriver\Shared\Driver\BaseHandler;
use Keboola\StorageDriver\Sqlite\SqliteConnectionManager;
use PDO;
use PDOException;
use RuntimeException;

final class ImportTableFromFileHandler extends BaseHandler
{
    private SqliteConnectionManager $connectionManager;

    public function __construct(SqliteConnectionManager $connectionManager)
    {
        parent::__construct();
        $this->connectionManager = $connectionManager;
    }

    /**
     * @param GenericBackendCredentials $credentials
     * @param ImportTableFromFileCommand $command
     */
    public function __invoke(
        Message $credentials,
        Message $command,
        array $features,
        Message $runtimeOptions,
    ): ?Message {
        assert($credentials instanceof GenericBackendCredentials);
        assert($command instanceof ImportTableFromFileCommand);

        assert($command->getPath()->count() === 1, 'ImportTableFromFileCommand.path is required and size must equal 1');
        assert($command->getTableName() !== '', 'ImportTableFromFileCommand.tableName is required');
        assert($command->getFileFormat() !== null, 'ImportTableFromFileCommand.fileFormat is required');

        $bucketName = $command->getPath()[0];
        $tableName = $command->getTableName();
        $projectId = $credentials->getPrincipal();

        $connection = $this->connectionManager->getConnection($projectId, $bucketName);

        $filePath = $command->getFileFormat()->getFilePath();
        if (!file_exists($filePath)) {
            throw new RuntimeException(sprintf('File not found: %s', $filePath));
        }

        $delimiter = $command->getFileFormat()->getColumnsDelimiter() ?: ',';
        $enclosure = $command->getFileFormat()->getColumnsEnclosure() ?: '"';
        
        $file = fopen($filePath, 'r');
        if ($file === false) {
            throw new RuntimeException(sprintf('Failed to open file: %s', $filePath));
        }

        $header = fgetcsv($file, 0, $delimiter, $enclosure);
        if ($header === false) {
            fclose($file);
            throw new RuntimeException('Failed to read CSV header');
        }

        $connection->beginTransaction();
        
        try {
            $placeholders = implode(',', array_fill(0, count($header), '?'));
            $columnNames = implode(',', array_map(fn($col) => sprintf('"%s"', $col), $header));
            
            $sql = sprintf(
                'INSERT INTO "%s" (%s) VALUES (%s)',
                $tableName,
                $columnNames,
                $placeholders
            );
            
            $stmt = $connection->prepare($sql);
            $rowCount = 0;

            while (($row = fgetcsv($file, 0, $delimiter, $enclosure)) !== false) {
                if (count($row) !== count($header)) {
                    continue;
                }
                
                $stmt->execute($row);
                $rowCount++;
            }

            $connection->commit();
            fclose($file);

            $response = new TableImportResponse();
            $response->setTableName($tableName);
            $response->setImportedRowsCount($rowCount);

            return $response;
        } catch (PDOException $e) {
            $connection->rollBack();
            fclose($file);
            throw new RuntimeException(
                sprintf('Failed to import data into table %s.%s: %s', $bucketName, $tableName, $e->getMessage()),
                0,
                $e
            );
        }
    }
}

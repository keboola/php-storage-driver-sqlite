<?php

declare(strict_types=1);

namespace Keboola\StorageDriver\Sqlite\Handler\Table\Export;

use Google\Protobuf\Internal\Message;
use Keboola\StorageDriver\Command\Table\TableExportToFileCommand;
use Keboola\StorageDriver\Command\Table\TableExportToFileResponse;
use Keboola\StorageDriver\Credentials\GenericBackendCredentials;
use Keboola\StorageDriver\Shared\Driver\BaseHandler;
use Keboola\StorageDriver\Sqlite\SqliteConnectionManager;
use PDO;
use PDOException;
use RuntimeException;

final class ExportTableToFileHandler extends BaseHandler
{
    private SqliteConnectionManager $connectionManager;

    public function __construct(SqliteConnectionManager $connectionManager)
    {
        parent::__construct();
        $this->connectionManager = $connectionManager;
    }

    /**
     * @param GenericBackendCredentials $credentials
     * @param TableExportToFileCommand $command
     */
    public function __invoke(
        Message $credentials,
        Message $command,
        array $features,
        Message $runtimeOptions,
    ): ?Message {
        assert($credentials instanceof GenericBackendCredentials);
        assert($command instanceof TableExportToFileCommand);

        assert($command->getPath()->count() === 1, 'TableExportToFileCommand.path is required and size must equal 1');
        assert($command->getTableName() !== '', 'TableExportToFileCommand.tableName is required');
        assert($command->getFileFormat() !== null, 'TableExportToFileCommand.fileFormat is required');

        $bucketName = $command->getPath()[0];
        $tableName = $command->getTableName();
        $projectId = $credentials->getPrincipal();

        $connection = $this->connectionManager->getConnection($projectId, $bucketName);

        $filePath = $command->getFileFormat()->getFilePath();
        $fileDir = dirname($filePath);
        
        if (!is_dir($fileDir)) {
            mkdir($fileDir, 0755, true);
        }

        $delimiter = $command->getFileFormat()->getColumnsDelimiter() ?: ',';
        $enclosure = $command->getFileFormat()->getColumnsEnclosure() ?: '"';

        $file = fopen($filePath, 'w');
        if ($file === false) {
            throw new RuntimeException(sprintf('Failed to create file: %s', $filePath));
        }

        try {
            $sql = sprintf('SELECT * FROM "%s"', $tableName);
            $stmt = $connection->query($sql);

            $headerWritten = false;
            $rowCount = 0;

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!$headerWritten) {
                    fputcsv($file, array_keys($row), $delimiter, $enclosure);
                    $headerWritten = true;
                }
                
                fputcsv($file, $row, $delimiter, $enclosure);
                $rowCount++;
            }

            fclose($file);

            $response = new TableExportToFileResponse();
            $response->setTableName($tableName);
            $response->setExportedRowsCount($rowCount);

            return $response;
        } catch (PDOException $e) {
            fclose($file);
            throw new RuntimeException(
                sprintf('Failed to export table %s.%s: %s', $bucketName, $tableName, $e->getMessage()),
                0,
                $e
            );
        }
    }
}

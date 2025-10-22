<?php

declare(strict_types=1);

namespace Keboola\StorageDriver\Sqlite\Handler\Table\Alter;

use Google\Protobuf\Internal\Message;
use Keboola\Datatype\Definition\Sqlite;
use Keboola\StorageDriver\Command\Table\AddColumnCommand;
use Keboola\StorageDriver\Credentials\GenericBackendCredentials;
use Keboola\StorageDriver\Shared\Driver\BaseHandler;
use Keboola\StorageDriver\Sqlite\SqliteConnectionManager;
use PDOException;
use RuntimeException;

final class AddColumnHandler extends BaseHandler
{
    private SqliteConnectionManager $connectionManager;

    public function __construct(SqliteConnectionManager $connectionManager)
    {
        parent::__construct();
        $this->connectionManager = $connectionManager;
    }

    /**
     * @param GenericBackendCredentials $credentials
     * @param AddColumnCommand $command
     */
    public function __invoke(
        Message $credentials,
        Message $command,
        array $features,
        Message $runtimeOptions,
    ): ?Message {
        assert($credentials instanceof GenericBackendCredentials);
        assert($command instanceof AddColumnCommand);

        assert($command->getPath()->count() === 1, 'AddColumnCommand.path is required and size must equal 1');
        assert($command->getTableName() !== '', 'AddColumnCommand.tableName is required');
        assert($command->getColumnName() !== '', 'AddColumnCommand.columnName is required');
        assert($command->getColumnDefinition() !== null, 'AddColumnCommand.columnDefinition is required');

        $bucketName = $command->getPath()[0];
        $tableName = $command->getTableName();
        $projectId = $credentials->getPrincipal();

        $connection = $this->connectionManager->getConnection($projectId, $bucketName);

        $columnDef = $command->getColumnDefinition();
        $sqliteType = new Sqlite($columnDef->getType(), [
            'length' => $columnDef->getLength() === '' ? null : $columnDef->getLength(),
            'nullable' => $columnDef->getNullable(),
        ]);

        $sql = sprintf(
            'ALTER TABLE "%s" ADD COLUMN "%s" %s',
            $tableName,
            $command->getColumnName(),
            $sqliteType->getSQLDefinition()
        );

        try {
            $connection->exec($sql);
        } catch (PDOException $e) {
            throw new RuntimeException(
                sprintf('Failed to add column %s to table %s.%s: %s', 
                    $command->getColumnName(), 
                    $bucketName, 
                    $tableName, 
                    $e->getMessage()
                ),
                0,
                $e
            );
        }

        return null;
    }
}

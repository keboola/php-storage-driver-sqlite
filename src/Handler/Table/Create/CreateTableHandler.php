<?php

declare(strict_types=1);

namespace Keboola\StorageDriver\Sqlite\Handler\Table\Create;

use Google\Protobuf\Internal\Message;
use Keboola\Datatype\Definition\Sqlite;
use Keboola\StorageDriver\Command\Info\ObjectInfoResponse;
use Keboola\StorageDriver\Command\Info\ObjectType;
use Keboola\StorageDriver\Command\Info\TableInfo;
use Keboola\StorageDriver\Command\Table\CreateTableCommand;
use Keboola\StorageDriver\Command\Table\TableColumnShared;
use Keboola\StorageDriver\Credentials\GenericBackendCredentials;
use Keboola\StorageDriver\Shared\Driver\BaseHandler;
use Keboola\StorageDriver\Sqlite\SqliteConnectionManager;
use PDOException;
use RuntimeException;

final class CreateTableHandler extends BaseHandler
{
    private SqliteConnectionManager $connectionManager;

    public function __construct(SqliteConnectionManager $connectionManager)
    {
        parent::__construct();
        $this->connectionManager = $connectionManager;
    }

    /**
     * @param GenericBackendCredentials $credentials
     * @param CreateTableCommand $command
     */
    public function __invoke(
        Message $credentials,
        Message $command,
        array $features,
        Message $runtimeOptions,
    ): ?Message {
        assert($credentials instanceof GenericBackendCredentials);
        assert($command instanceof CreateTableCommand);

        assert($command->getPath()->count() === 1, 'CreateTableCommand.path is required and size must equal 1');
        assert($command->getTableName() !== '', 'CreateTableCommand.tableName is required');
        assert($command->getColumns()->count() > 0, 'CreateTableCommand.columns is required');

        $bucketName = $command->getPath()[0];
        $tableName = $command->getTableName();
        $projectId = $credentials->getPrincipal();

        $connection = $this->connectionManager->getConnection($projectId, $bucketName);

        $columnDefinitions = [];
        $primaryKeys = [];

        foreach ($command->getColumns() as $column) {
            assert($column instanceof TableColumnShared);
            assert($column->getName() !== '', 'TableColumnShared.name is required');
            assert($column->getType() !== '', 'TableColumnShared.type is required');

            $columnDef = new Sqlite($column->getType(), [
                'length' => $column->getLength() === '' ? null : $column->getLength(),
                'nullable' => $column->getNullable(),
            ]);

            $columnDefinitions[] = sprintf(
                '"%s" %s',
                $column->getName(),
                $columnDef->getSQLDefinition()
            );
        }

        if ($command->getPrimaryKeysNames()->count() > 0) {
            $pkColumns = [];
            foreach ($command->getPrimaryKeysNames() as $pkName) {
                $pkColumns[] = sprintf('"%s"', $pkName);
                $primaryKeys[] = $pkName;
            }
            $columnDefinitions[] = sprintf('PRIMARY KEY (%s)', implode(', ', $pkColumns));
        }

        $sql = sprintf(
            'CREATE TABLE "%s" (%s)',
            $tableName,
            implode(', ', $columnDefinitions)
        );

        try {
            $connection->exec($sql);
        } catch (PDOException $e) {
            throw new RuntimeException(
                sprintf('Failed to create table %s.%s: %s', $bucketName, $tableName, $e->getMessage()),
                0,
                $e
            );
        }

        $tableInfo = new TableInfo();
        $tableInfo->setTableName($tableName);
        $tableInfo->setPath($command->getPath());
        
        foreach ($command->getColumns() as $column) {
            $tableInfo->getColumns()[] = $column;
        }
        
        foreach ($primaryKeys as $pk) {
            $tableInfo->getPrimaryKeysNames()[] = $pk;
        }

        return (new ObjectInfoResponse())
            ->setPath($command->getPath())
            ->setObjectType(ObjectType::TABLE)
            ->setTableInfo($tableInfo);
    }
}

<?php

declare(strict_types=1);

namespace Keboola\StorageDriver\Sqlite\Handler\Table\Alter;

use Google\Protobuf\Internal\Message;
use Keboola\StorageDriver\Command\Table\DropColumnCommand;
use Keboola\StorageDriver\Credentials\GenericBackendCredentials;
use Keboola\StorageDriver\Shared\Driver\BaseHandler;
use Keboola\StorageDriver\Sqlite\SqliteConnectionManager;
use PDOException;
use RuntimeException;

final class DropColumnHandler extends BaseHandler
{
    private SqliteConnectionManager $connectionManager;

    public function __construct(SqliteConnectionManager $connectionManager)
    {
        parent::__construct();
        $this->connectionManager = $connectionManager;
    }

    /**
     * @param GenericBackendCredentials $credentials
     * @param DropColumnCommand $command
     */
    public function __invoke(
        Message $credentials,
        Message $command,
        array $features,
        Message $runtimeOptions,
    ): ?Message {
        assert($credentials instanceof GenericBackendCredentials);
        assert($command instanceof DropColumnCommand);

        assert($command->getPath()->count() === 1, 'DropColumnCommand.path is required and size must equal 1');
        assert($command->getTableName() !== '', 'DropColumnCommand.tableName is required');
        assert($command->getColumnName() !== '', 'DropColumnCommand.columnName is required');

        $bucketName = $command->getPath()[0];
        $tableName = $command->getTableName();
        $projectId = $credentials->getPrincipal();

        $connection = $this->connectionManager->getConnection($projectId, $bucketName);

        $sql = sprintf(
            'ALTER TABLE "%s" DROP COLUMN "%s"',
            $tableName,
            $command->getColumnName()
        );

        try {
            $connection->exec($sql);
        } catch (PDOException $e) {
            throw new RuntimeException(
                sprintf('Failed to drop column %s from table %s.%s: %s', 
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

<?php

declare(strict_types=1);

namespace Keboola\StorageDriver\Sqlite\Handler\Table\Drop;

use Google\Protobuf\Internal\Message;
use Keboola\StorageDriver\Command\Table\DropTableCommand;
use Keboola\StorageDriver\Credentials\GenericBackendCredentials;
use Keboola\StorageDriver\Shared\Driver\BaseHandler;
use Keboola\StorageDriver\Sqlite\SqliteConnectionManager;
use PDOException;
use RuntimeException;

final class DropTableHandler extends BaseHandler
{
    private SqliteConnectionManager $connectionManager;

    public function __construct(SqliteConnectionManager $connectionManager)
    {
        parent::__construct();
        $this->connectionManager = $connectionManager;
    }

    /**
     * @param GenericBackendCredentials $credentials
     * @param DropTableCommand $command
     */
    public function __invoke(
        Message $credentials,
        Message $command,
        array $features,
        Message $runtimeOptions,
    ): ?Message {
        assert($credentials instanceof GenericBackendCredentials);
        assert($command instanceof DropTableCommand);

        assert($command->getPath()->count() === 1, 'DropTableCommand.path is required and size must equal 1');
        assert($command->getTableName() !== '', 'DropTableCommand.tableName is required');

        $bucketName = $command->getPath()[0];
        $tableName = $command->getTableName();
        $projectId = $credentials->getPrincipal();

        $connection = $this->connectionManager->getConnection($projectId, $bucketName);

        $sql = sprintf('DROP TABLE IF EXISTS "%s"', $tableName);

        try {
            $connection->exec($sql);
        } catch (PDOException $e) {
            throw new RuntimeException(
                sprintf('Failed to drop table %s.%s: %s', $bucketName, $tableName, $e->getMessage()),
                0,
                $e
            );
        }

        return null;
    }
}

<?php

declare(strict_types=1);

namespace Keboola\StorageDriver\Sqlite\Handler\Info;

use Google\Protobuf\Internal\Message;
use Keboola\StorageDriver\Command\Info\ObjectInfoCommand;
use Keboola\StorageDriver\Command\Info\ObjectInfoResponse;
use Keboola\StorageDriver\Command\Info\ObjectType;
use Keboola\StorageDriver\Command\Info\TableInfo;
use Keboola\StorageDriver\Command\Table\TableColumnShared;
use Keboola\StorageDriver\Credentials\GenericBackendCredentials;
use Keboola\StorageDriver\Shared\Driver\BaseHandler;
use Keboola\StorageDriver\Sqlite\SqliteConnectionManager;
use PDO;
use PDOException;
use RuntimeException;

final class ObjectInfoHandler extends BaseHandler
{
    private SqliteConnectionManager $connectionManager;

    public function __construct(SqliteConnectionManager $connectionManager)
    {
        parent::__construct();
        $this->connectionManager = $connectionManager;
    }

    /**
     * @param GenericBackendCredentials $credentials
     * @param ObjectInfoCommand $command
     */
    public function __invoke(
        Message $credentials,
        Message $command,
        array $features,
        Message $runtimeOptions,
    ): ?Message {
        assert($credentials instanceof GenericBackendCredentials);
        assert($command instanceof ObjectInfoCommand);

        $projectId = $credentials->getPrincipal();
        $response = new ObjectInfoResponse();
        $response->setPath($command->getPath());

        if ($command->getPath()->count() === 0) {
            $response->setObjectType(ObjectType::BUCKET);
            return $response;
        }

        if ($command->getPath()->count() === 1 && $command->getTableName() === '') {
            $response->setObjectType(ObjectType::BUCKET);
            return $response;
        }

        if ($command->getPath()->count() === 1 && $command->getTableName() !== '') {
            $bucketName = $command->getPath()[0];
            $tableName = $command->getTableName();

            $connection = $this->connectionManager->getConnection($projectId, $bucketName);

            try {
                $stmt = $connection->prepare('PRAGMA table_info("' . $tableName . '")');
                $stmt->execute();
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $tableInfo = new TableInfo();
                $tableInfo->setTableName($tableName);
                $tableInfo->setPath($command->getPath());

                foreach ($columns as $column) {
                    $col = new TableColumnShared();
                    $col->setName($column['name']);
                    $col->setType($column['type']);
                    $col->setNullable($column['notnull'] === '0');
                    
                    $tableInfo->getColumns()[] = $col;
                    
                    if ($column['pk'] === '1') {
                        $tableInfo->getPrimaryKeysNames()[] = $column['name'];
                    }
                }

                $response->setObjectType(ObjectType::TABLE);
                $response->setTableInfo($tableInfo);

                return $response;
            } catch (PDOException $e) {
                throw new RuntimeException(
                    sprintf('Failed to get info for table %s.%s: %s', $bucketName, $tableName, $e->getMessage()),
                    0,
                    $e
                );
            }
        }

        return $response;
    }
}

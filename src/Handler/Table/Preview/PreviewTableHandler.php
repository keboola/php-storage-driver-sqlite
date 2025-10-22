<?php

declare(strict_types=1);

namespace Keboola\StorageDriver\Sqlite\Handler\Table\Preview;

use Google\Protobuf\Internal\Message;
use Keboola\StorageDriver\Command\Table\PreviewTableCommand;
use Keboola\StorageDriver\Command\Table\PreviewTableResponse;
use Keboola\StorageDriver\Command\Table\TableColumn;
use Keboola\StorageDriver\Credentials\GenericBackendCredentials;
use Keboola\StorageDriver\Shared\Driver\BaseHandler;
use Keboola\StorageDriver\Sqlite\SqliteConnectionManager;
use PDO;
use PDOException;
use RuntimeException;

final class PreviewTableHandler extends BaseHandler
{
    private SqliteConnectionManager $connectionManager;

    public function __construct(SqliteConnectionManager $connectionManager)
    {
        parent::__construct();
        $this->connectionManager = $connectionManager;
    }

    /**
     * @param GenericBackendCredentials $credentials
     * @param PreviewTableCommand $command
     */
    public function __invoke(
        Message $credentials,
        Message $command,
        array $features,
        Message $runtimeOptions,
    ): ?Message {
        assert($credentials instanceof GenericBackendCredentials);
        assert($command instanceof PreviewTableCommand);

        assert($command->getPath()->count() === 1, 'PreviewTableCommand.path is required and size must equal 1');
        assert($command->getTableName() !== '', 'PreviewTableCommand.tableName is required');

        $bucketName = $command->getPath()[0];
        $tableName = $command->getTableName();
        $projectId = $credentials->getPrincipal();

        $connection = $this->connectionManager->getConnection($projectId, $bucketName);

        $columns = $command->getColumns()->count() > 0 
            ? array_map(fn($col) => sprintf('"%s"', $col), iterator_to_array($command->getColumns()))
            : ['*'];

        $orderBy = $command->getOrderBy()->count() > 0
            ? ' ORDER BY ' . implode(', ', array_map(fn($col) => sprintf('"%s"', $col), iterator_to_array($command->getOrderBy())))
            : '';

        $limit = $command->getLimit() > 0 ? ' LIMIT ' . $command->getLimit() : ' LIMIT 100';

        $sql = sprintf(
            'SELECT %s FROM "%s"%s%s',
            implode(', ', $columns),
            $tableName,
            $orderBy,
            $limit
        );

        try {
            $stmt = $connection->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new RuntimeException(
                sprintf('Failed to preview table %s.%s: %s', $bucketName, $tableName, $e->getMessage()),
                0,
                $e
            );
        }

        $response = new PreviewTableResponse();

        if (count($rows) > 0) {
            $columnNames = array_keys($rows[0]);
            foreach ($columnNames as $columnName) {
                $column = new TableColumn();
                $column->setName($columnName);
                $response->getColumns()[] = $column;
            }

            foreach ($rows as $row) {
                $response->getRows()[] = implode(',', array_map(fn($v) => $v ?? '', $row));
            }
        }

        return $response;
    }
}

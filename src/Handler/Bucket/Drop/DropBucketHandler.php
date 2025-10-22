<?php

declare(strict_types=1);

namespace Keboola\StorageDriver\Sqlite\Handler\Bucket\Drop;

use Google\Protobuf\Internal\Message;
use Keboola\StorageDriver\Command\Bucket\DropBucketCommand;
use Keboola\StorageDriver\Credentials\GenericBackendCredentials;
use Keboola\StorageDriver\Shared\Driver\BaseHandler;
use Keboola\StorageDriver\Sqlite\SqliteConnectionManager;

final class DropBucketHandler extends BaseHandler
{
    private SqliteConnectionManager $connectionManager;

    public function __construct(SqliteConnectionManager $connectionManager)
    {
        parent::__construct();
        $this->connectionManager = $connectionManager;
    }

    /**
     * @param GenericBackendCredentials $credentials
     * @param DropBucketCommand $command
     */
    public function __invoke(
        Message $credentials,
        Message $command,
        array $features,
        Message $runtimeOptions,
    ): ?Message {
        assert($credentials instanceof GenericBackendCredentials);
        assert($command instanceof DropBucketCommand);

        assert($command->getPath()->count() === 0, 'DropBucketCommand.path must be empty');
        assert($command->getBucketName() !== '', 'DropBucketCommand.bucketName is required');

        $bucketName = $command->getBucketName();
        $projectId = $credentials->getPrincipal();

        $this->connectionManager->dropDatabase($projectId, $bucketName);

        return null;
    }
}

<?php

declare(strict_types=1);

namespace Keboola\StorageDriver\Sqlite\Handler\Bucket\Create;

use Google\Protobuf\Internal\Message;
use Keboola\StorageDriver\Command\Bucket\CreateBucketCommand;
use Keboola\StorageDriver\Command\Info\BucketInfo;
use Keboola\StorageDriver\Command\Info\ObjectInfoResponse;
use Keboola\StorageDriver\Command\Info\ObjectType;
use Keboola\StorageDriver\Credentials\GenericBackendCredentials;
use Keboola\StorageDriver\Shared\Driver\BaseHandler;
use Keboola\StorageDriver\Sqlite\SqliteConnectionManager;

final class CreateBucketHandler extends BaseHandler
{
    private SqliteConnectionManager $connectionManager;

    public function __construct(SqliteConnectionManager $connectionManager)
    {
        parent::__construct();
        $this->connectionManager = $connectionManager;
    }

    /**
     * @param GenericBackendCredentials $credentials
     * @param CreateBucketCommand $command
     */
    public function __invoke(
        Message $credentials,
        Message $command,
        array $features,
        Message $runtimeOptions,
    ): ?Message {
        assert($credentials instanceof GenericBackendCredentials);
        assert($command instanceof CreateBucketCommand);

        assert($command->getPath()->count() === 0, 'CreateBucketCommand.path must be empty');
        assert($command->getBucketName() !== '', 'CreateBucketCommand.bucketName is required');

        $bucketName = $command->getBucketName();
        $projectId = $credentials->getPrincipal();

        $this->connectionManager->createDatabase($projectId, $bucketName);

        $bucketInfo = new BucketInfo();
        $bucketInfo->setBucketName($bucketName);

        return (new ObjectInfoResponse())
            ->setPath([])
            ->setObjectType(ObjectType::BUCKET)
            ->setBucketInfo($bucketInfo);
    }
}

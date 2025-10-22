<?php

declare(strict_types=1);

namespace Keboola\StorageDriver\Sqlite;

use Aws\S3\S3Client;
use Google\Protobuf\Any;
use Google\Protobuf\Internal\Message;
use Keboola\StorageDriver\Command\Common\DriverResponse;
use Keboola\StorageDriver\Contract\Driver\ClientInterface;
use Keboola\StorageDriver\Sqlite\Handler\HandlerFactory;
use Keboola\StorageDriver\Sqlite\Storage\CloudStorageInterface;
use Keboola\StorageDriver\Sqlite\Storage\DistributedLock;
use Keboola\StorageDriver\Sqlite\Storage\S3StorageAdapter;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class SqliteDriverClient implements ClientInterface
{
    protected LoggerInterface $internalLogger;
    
    protected SqliteConnectionManager $connectionManager;

    public function __construct(
        ?string $storageRoot = null,
        ?LoggerInterface $internalLogger = null,
        ?CloudStorageInterface $cloudStorage = null,
        ?DistributedLock $distributedLock = null
    ) {
        if ($internalLogger === null) {
            $this->internalLogger = new NullLogger();
        } else {
            $this->internalLogger = $internalLogger;
        }
        
        if ($storageRoot === null) {
            $storageRoot = sys_get_temp_dir() . '/keboola-sqlite-storage';
        }
        
        $this->connectionManager = new SqliteConnectionManager($storageRoot, $cloudStorage, $distributedLock);
    }

    public static function createWithS3Storage(
        string $s3Bucket,
        string $s3Region,
        ?string $s3Endpoint = null,
        ?string $storageRoot = null,
        ?LoggerInterface $internalLogger = null
    ): self {
        $s3Config = [
            'version' => 'latest',
            'region' => $s3Region,
        ];

        if ($s3Endpoint !== null) {
            $s3Config['endpoint'] = $s3Endpoint;
        }

        $s3Client = new S3Client($s3Config);
        $cloudStorage = new S3StorageAdapter($s3Client, $s3Bucket);
        $distributedLock = new DistributedLock($cloudStorage);

        if ($storageRoot === null) {
            $storageRoot = 'sqlite-databases';
        }

        return new self($storageRoot, $internalLogger, $cloudStorage, $distributedLock);
    }

    public function getConnectionManager(): SqliteConnectionManager
    {
        return $this->connectionManager;
    }

    /**
     * @param string[] $features
     */
    public function runCommand(
        Message $credentials,
        Message $command,
        array $features,
        Message $runtimeOptions,
    ): ?Message {
        $handler = HandlerFactory::create(
            $command,
            $this->connectionManager,
            $this->internalLogger,
        );

        $handledResponse = $handler(
            $credentials,
            $command,
            $features,
            $runtimeOptions,
        );

        $response = new DriverResponse();
        if ($handledResponse !== null) {
            $any = new Any();
            $any->pack($handledResponse);
            $response->setCommandResponse($any);
        }

        return $response;
    }
}

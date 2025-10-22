<?php

declare(strict_types=1);

namespace Keboola\StorageDriver\Sqlite;

use Google\Protobuf\Any;
use Google\Protobuf\Internal\Message;
use Keboola\StorageDriver\Command\Common\DriverResponse;
use Keboola\StorageDriver\Contract\Driver\ClientInterface;
use Keboola\StorageDriver\Sqlite\Handler\HandlerFactory;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class SqliteDriverClient implements ClientInterface
{
    protected LoggerInterface $internalLogger;
    
    protected SqliteConnectionManager $connectionManager;

    public function __construct(
        ?string $storageRoot = null,
        ?LoggerInterface $internalLogger = null
    ) {
        if ($internalLogger === null) {
            $this->internalLogger = new NullLogger();
        } else {
            $this->internalLogger = $internalLogger;
        }
        
        if ($storageRoot === null) {
            $storageRoot = sys_get_temp_dir() . '/keboola-sqlite-storage';
        }
        
        $this->connectionManager = new SqliteConnectionManager($storageRoot);
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

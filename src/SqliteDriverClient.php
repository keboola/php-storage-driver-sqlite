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

    public function __construct(?LoggerInterface $internalLogger = null)
    {
        if ($internalLogger === null) {
            $this->internalLogger = new NullLogger();
        } else {
            $this->internalLogger = $internalLogger;
        }
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

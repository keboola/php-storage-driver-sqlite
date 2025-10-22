<?php

declare(strict_types=1);

namespace Keboola\StorageDriver\Sqlite\Handler;

use Google\Protobuf\Internal\Message;
use Keboola\StorageDriver\Contract\Driver\Command\DriverCommandHandlerInterface;
use Keboola\StorageDriver\Shared\Driver\BaseHandler;

class EmptyHandler extends BaseHandler implements DriverCommandHandlerInterface
{
    public function __invoke(
        Message $credentials,
        Message $command,
        array $features,
        Message $runtimeOptions,
    ): ?Message {
        return null;
    }
}

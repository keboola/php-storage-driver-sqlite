<?php

declare(strict_types=1);

namespace Keboola\StorageDriver\Sqlite\Handler;

use Google\Protobuf\Internal\Message;
use Keboola\StorageDriver\Command\Backend\InitBackendCommand;
use Keboola\StorageDriver\Command\Backend\RemoveBackendCommand;
use Keboola\StorageDriver\Command\Bucket\CreateBucketCommand;
use Keboola\StorageDriver\Command\Bucket\DropBucketCommand;
use Keboola\StorageDriver\Command\Info\ObjectInfoCommand;
use Keboola\StorageDriver\Command\Project\CreateDevBranchCommand;
use Keboola\StorageDriver\Command\Project\CreateProjectCommand;
use Keboola\StorageDriver\Command\Project\DropDevBranchCommand;
use Keboola\StorageDriver\Command\Project\DropProjectCommand;
use Keboola\StorageDriver\Command\Table\AddColumnCommand;
use Keboola\StorageDriver\Command\Table\CreateTableCommand;
use Keboola\StorageDriver\Command\Table\DropColumnCommand;
use Keboola\StorageDriver\Command\Table\DropTableCommand;
use Keboola\StorageDriver\Command\Table\PreviewTableCommand;
use Keboola\StorageDriver\Command\Table\TableImportFromFileCommand;
use Keboola\StorageDriver\Command\Workspace\CreateWorkspaceCommand;
use Keboola\StorageDriver\Command\Workspace\DropWorkspaceCommand;
use Keboola\StorageDriver\Contract\Driver\Command\DriverCommandHandlerInterface;
use Keboola\StorageDriver\Shared\Driver\Exception\CommandNotSupportedException;
use Psr\Log\LoggerInterface;

final class HandlerFactory
{
    public static function create(
        Message $command,
        LoggerInterface $internalLogger,
    ): DriverCommandHandlerInterface {
        $handler = match ($command::class) {
            InitBackendCommand::class,
            RemoveBackendCommand::class,
            CreateProjectCommand::class,
            DropProjectCommand::class,
            CreateBucketCommand::class,
            DropBucketCommand::class,
            CreateTableCommand::class,
            DropTableCommand::class,
            AddColumnCommand::class,
            DropColumnCommand::class,
            TableImportFromFileCommand::class,
            PreviewTableCommand::class,
            CreateWorkspaceCommand::class,
            DropWorkspaceCommand::class,
            ObjectInfoCommand::class,
            CreateDevBranchCommand::class,
            DropDevBranchCommand::class => new EmptyHandler(),
            default => throw new CommandNotSupportedException($command::class),
        };

        $handler->setInternalLogger($internalLogger);

        return $handler;
    }
}

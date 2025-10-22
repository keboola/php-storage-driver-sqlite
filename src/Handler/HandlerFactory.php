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
use Keboola\StorageDriver\Command\Table\TableExportToFileCommand;
use Keboola\StorageDriver\Command\Table\TableImportFromFileCommand;
use Keboola\StorageDriver\Command\Workspace\CreateWorkspaceCommand;
use Keboola\StorageDriver\Command\Workspace\DropWorkspaceCommand;
use Keboola\StorageDriver\Contract\Driver\Command\DriverCommandHandlerInterface;
use Keboola\StorageDriver\Shared\Driver\Exception\CommandNotSupportedException;
use Keboola\StorageDriver\Sqlite\Handler\Backend\Init\InitBackendHandler;
use Keboola\StorageDriver\Sqlite\Handler\Backend\Remove\RemoveBackendHandler;
use Keboola\StorageDriver\Sqlite\Handler\Bucket\Create\CreateBucketHandler;
use Keboola\StorageDriver\Sqlite\Handler\Bucket\Drop\DropBucketHandler;
use Keboola\StorageDriver\Sqlite\Handler\Info\ObjectInfoHandler;
use Keboola\StorageDriver\Sqlite\Handler\Project\Create\CreateProjectHandler;
use Keboola\StorageDriver\Sqlite\Handler\Project\Drop\DropProjectHandler;
use Keboola\StorageDriver\Sqlite\Handler\Table\Alter\AddColumnHandler;
use Keboola\StorageDriver\Sqlite\Handler\Table\Alter\DropColumnHandler;
use Keboola\StorageDriver\Sqlite\Handler\Table\Create\CreateTableHandler;
use Keboola\StorageDriver\Sqlite\Handler\Table\Drop\DropTableHandler;
use Keboola\StorageDriver\Sqlite\Handler\Table\Export\ExportTableToFileHandler;
use Keboola\StorageDriver\Sqlite\Handler\Table\Import\ImportTableFromFileHandler;
use Keboola\StorageDriver\Sqlite\Handler\Table\Preview\PreviewTableHandler;
use Keboola\StorageDriver\Sqlite\Handler\Workspace\Create\CreateWorkspaceHandler;
use Keboola\StorageDriver\Sqlite\Handler\Workspace\Drop\DropWorkspaceHandler;
use Keboola\StorageDriver\Sqlite\SqliteConnectionManager;
use Psr\Log\LoggerInterface;

final class HandlerFactory
{
    public static function create(
        Message $command,
        SqliteConnectionManager $connectionManager,
        LoggerInterface $internalLogger,
    ): DriverCommandHandlerInterface {
        $handler = match ($command::class) {
            InitBackendCommand::class => new InitBackendHandler($connectionManager),
            RemoveBackendCommand::class => new RemoveBackendHandler($connectionManager),
            CreateProjectCommand::class => new CreateProjectHandler($connectionManager),
            DropProjectCommand::class => new DropProjectHandler($connectionManager),
            CreateBucketCommand::class => new CreateBucketHandler($connectionManager),
            DropBucketCommand::class => new DropBucketHandler($connectionManager),
            CreateTableCommand::class => new CreateTableHandler($connectionManager),
            DropTableCommand::class => new DropTableHandler($connectionManager),
            AddColumnCommand::class => new AddColumnHandler($connectionManager),
            DropColumnCommand::class => new DropColumnHandler($connectionManager),
            TableImportFromFileCommand::class => new ImportTableFromFileHandler($connectionManager),
            TableExportToFileCommand::class => new ExportTableToFileHandler($connectionManager),
            PreviewTableCommand::class => new PreviewTableHandler($connectionManager),
            CreateWorkspaceCommand::class => new CreateWorkspaceHandler($connectionManager),
            DropWorkspaceCommand::class => new DropWorkspaceHandler($connectionManager),
            ObjectInfoCommand::class => new ObjectInfoHandler($connectionManager),
            CreateDevBranchCommand::class,
            DropDevBranchCommand::class => new EmptyHandler(),
            default => throw new CommandNotSupportedException($command::class),
        };

        $handler->setInternalLogger($internalLogger);

        return $handler;
    }
}

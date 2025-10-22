<?php

declare(strict_types=1);

namespace Keboola\StorageDriver\Sqlite\Handler\Workspace\Drop;

use Google\Protobuf\Internal\Message;
use Keboola\StorageDriver\Command\Workspace\DropWorkspaceCommand;
use Keboola\StorageDriver\Credentials\GenericBackendCredentials;
use Keboola\StorageDriver\Shared\Driver\BaseHandler;
use Keboola\StorageDriver\Sqlite\SqliteConnectionManager;

final class DropWorkspaceHandler extends BaseHandler
{
    private SqliteConnectionManager $connectionManager;

    public function __construct(SqliteConnectionManager $connectionManager)
    {
        parent::__construct();
        $this->connectionManager = $connectionManager;
    }

    /**
     * @param GenericBackendCredentials $credentials
     * @param DropWorkspaceCommand $command
     */
    public function __invoke(
        Message $credentials,
        Message $command,
        array $features,
        Message $runtimeOptions,
    ): ?Message {
        assert($credentials instanceof GenericBackendCredentials);
        assert($command instanceof DropWorkspaceCommand);

        $projectId = $credentials->getPrincipal();
        $workspaceName = $command->getWorkspaceId();

        $this->connectionManager->dropDatabase($projectId, $workspaceName);

        return null;
    }
}

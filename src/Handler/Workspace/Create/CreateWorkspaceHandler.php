<?php

declare(strict_types=1);

namespace Keboola\StorageDriver\Sqlite\Handler\Workspace\Create;

use Google\Protobuf\Internal\Message;
use Keboola\StorageDriver\Command\Info\ObjectInfoResponse;
use Keboola\StorageDriver\Command\Info\ObjectType;
use Keboola\StorageDriver\Command\Workspace\CreateWorkspaceCommand;
use Keboola\StorageDriver\Command\Workspace\CreateWorkspaceResponse;
use Keboola\StorageDriver\Credentials\GenericBackendCredentials;
use Keboola\StorageDriver\Shared\Driver\BaseHandler;
use Keboola\StorageDriver\Sqlite\SqliteConnectionManager;

final class CreateWorkspaceHandler extends BaseHandler
{
    private SqliteConnectionManager $connectionManager;

    public function __construct(SqliteConnectionManager $connectionManager)
    {
        parent::__construct();
        $this->connectionManager = $connectionManager;
    }

    /**
     * @param GenericBackendCredentials $credentials
     * @param CreateWorkspaceCommand $command
     */
    public function __invoke(
        Message $credentials,
        Message $command,
        array $features,
        Message $runtimeOptions,
    ): ?Message {
        assert($credentials instanceof GenericBackendCredentials);
        assert($command instanceof CreateWorkspaceCommand);

        $projectId = $credentials->getPrincipal();
        $workspaceName = 'workspace_' . uniqid();

        $this->connectionManager->createDatabase($projectId, $workspaceName);

        $workspaceCredentials = new GenericBackendCredentials();
        $workspaceCredentials->setPrincipal($projectId);
        $workspaceCredentials->setSecret($workspaceName);

        $response = new CreateWorkspaceResponse();
        $response->setWorkspaceId($workspaceName);
        $response->setCredentials($workspaceCredentials);

        return $response;
    }
}

<?php

declare(strict_types=1);

namespace Keboola\StorageDriver\Sqlite\Handler\Project\Create;

use Google\Protobuf\Internal\Message;
use Keboola\StorageDriver\Command\Project\CreateProjectCommand;
use Keboola\StorageDriver\Command\Project\CreateProjectResponse;
use Keboola\StorageDriver\Credentials\GenericBackendCredentials;
use Keboola\StorageDriver\Shared\Driver\BaseHandler;
use Keboola\StorageDriver\Sqlite\SqliteConnectionManager;

final class CreateProjectHandler extends BaseHandler
{
    private SqliteConnectionManager $connectionManager;

    public function __construct(SqliteConnectionManager $connectionManager)
    {
        parent::__construct();
        $this->connectionManager = $connectionManager;
    }

    /**
     * @param GenericBackendCredentials $credentials
     * @param CreateProjectCommand $command
     */
    public function __invoke(
        Message $credentials,
        Message $command,
        array $features,
        Message $runtimeOptions,
    ): ?Message {
        assert($credentials instanceof GenericBackendCredentials);
        assert($command instanceof CreateProjectCommand);

        $projectId = $command->getProjectId();

        $this->connectionManager->createDatabase($projectId, 'main');

        $projectCredentials = new GenericBackendCredentials();
        $projectCredentials->setPrincipal($projectId);
        $projectCredentials->setSecret('');

        $response = new CreateProjectResponse();
        $response->setProjectId($projectId);
        $response->setProjectCredentials($projectCredentials);

        return $response;
    }
}

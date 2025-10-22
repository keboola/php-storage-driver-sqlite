<?php

declare(strict_types=1);

namespace Keboola\StorageDriver\Sqlite\Handler\Project\Drop;

use Google\Protobuf\Internal\Message;
use Keboola\StorageDriver\Command\Project\DropProjectCommand;
use Keboola\StorageDriver\Credentials\GenericBackendCredentials;
use Keboola\StorageDriver\Shared\Driver\BaseHandler;
use Keboola\StorageDriver\Sqlite\SqliteConnectionManager;

final class DropProjectHandler extends BaseHandler
{
    private SqliteConnectionManager $connectionManager;

    public function __construct(SqliteConnectionManager $connectionManager)
    {
        parent::__construct();
        $this->connectionManager = $connectionManager;
    }

    /**
     * @param GenericBackendCredentials $credentials
     * @param DropProjectCommand $command
     */
    public function __invoke(
        Message $credentials,
        Message $command,
        array $features,
        Message $runtimeOptions,
    ): ?Message {
        assert($credentials instanceof GenericBackendCredentials);
        assert($command instanceof DropProjectCommand);

        $projectId = $command->getProjectId();

        $projectDir = $this->connectionManager->getDatabasePath($projectId, 'main');
        $projectDir = dirname($projectDir);

        if (is_dir($projectDir)) {
            $files = glob($projectDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($projectDir);
        }

        return null;
    }
}

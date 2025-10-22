<?php

declare(strict_types=1);

namespace Keboola\StorageDriver\Sqlite\Handler\Backend\Remove;

use Google\Protobuf\Internal\Message;
use Keboola\StorageDriver\Command\Backend\RemoveBackendCommand;
use Keboola\StorageDriver\Credentials\GenericBackendCredentials;
use Keboola\StorageDriver\Shared\Driver\BaseHandler;
use Keboola\StorageDriver\Sqlite\SqliteConnectionManager;

final class RemoveBackendHandler extends BaseHandler
{
    private SqliteConnectionManager $connectionManager;

    public function __construct(SqliteConnectionManager $connectionManager)
    {
        parent::__construct();
        $this->connectionManager = $connectionManager;
    }

    /**
     * @param GenericBackendCredentials $credentials
     * @param RemoveBackendCommand $command
     */
    public function __invoke(
        Message $credentials,
        Message $command,
        array $features,
        Message $runtimeOptions,
    ): ?Message {
        assert($credentials instanceof GenericBackendCredentials);
        assert($command instanceof RemoveBackendCommand);

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

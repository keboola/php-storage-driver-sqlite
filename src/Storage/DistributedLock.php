<?php

declare(strict_types=1);

namespace Keboola\StorageDriver\Sqlite\Storage;

use RuntimeException;

class DistributedLock
{
    private CloudStorageInterface $storage;
    
    private string $lockPrefix;
    
    private int $lockTimeout;
    
    private int $maxWaitTime;

    public function __construct(
        CloudStorageInterface $storage,
        string $lockPrefix = 'locks/',
        int $lockTimeout = 300,
        int $maxWaitTime = 60
    ) {
        $this->storage = $storage;
        $this->lockPrefix = $lockPrefix;
        $this->lockTimeout = $lockTimeout;
        $this->maxWaitTime = $maxWaitTime;
    }

    public function acquireLock(string $resourceId): string
    {
        $lockId = uniqid('lock_', true);
        $lockKey = $this->lockPrefix . $resourceId . '.lock';
        $startTime = time();

        while (true) {
            if (!$this->storage->fileExists($lockKey)) {
                $lockData = json_encode([
                    'lock_id' => $lockId,
                    'acquired_at' => time(),
                    'expires_at' => time() + $this->lockTimeout,
                    'pid' => getmypid(),
                    'hostname' => gethostname(),
                ]);

                $tempFile = sys_get_temp_dir() . '/' . $lockId;
                file_put_contents($tempFile, $lockData);

                try {
                    $this->storage->uploadFile($tempFile, $lockKey);
                    unlink($tempFile);
                    return $lockId;
                } catch (\Exception $e) {
                    if (file_exists($tempFile)) {
                        unlink($tempFile);
                    }
                }
            }

            $tempFile = sys_get_temp_dir() . '/' . uniqid('lock_check_');
            try {
                $this->storage->downloadFile($lockKey, $tempFile);
                $lockData = json_decode(file_get_contents($tempFile), true);
                unlink($tempFile);

                if (isset($lockData['expires_at']) && $lockData['expires_at'] < time()) {
                    $this->storage->deleteFile($lockKey);
                    continue;
                }
            } catch (\Exception $e) {
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }

            if (time() - $startTime > $this->maxWaitTime) {
                throw new RuntimeException(
                    sprintf('Failed to acquire lock for resource %s after %d seconds', $resourceId, $this->maxWaitTime)
                );
            }

            usleep(100000);
        }
    }

    public function releaseLock(string $resourceId, string $lockId): void
    {
        $lockKey = $this->lockPrefix . $resourceId . '.lock';

        if (!$this->storage->fileExists($lockKey)) {
            return;
        }

        $tempFile = sys_get_temp_dir() . '/' . uniqid('lock_release_');
        try {
            $this->storage->downloadFile($lockKey, $tempFile);
            $lockData = json_decode(file_get_contents($tempFile), true);
            unlink($tempFile);

            if (isset($lockData['lock_id']) && $lockData['lock_id'] === $lockId) {
                $this->storage->deleteFile($lockKey);
            }
        } catch (\Exception $e) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function withLock(string $resourceId, callable $callback)
    {
        $lockId = $this->acquireLock($resourceId);
        
        try {
            return $callback();
        } finally {
            $this->releaseLock($resourceId, $lockId);
        }
    }
}

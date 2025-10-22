<?php

declare(strict_types=1);

namespace Keboola\StorageDriver\Sqlite\Storage;

interface CloudStorageInterface
{
    public function fileExists(string $key): bool;

    public function downloadFile(string $key, string $localPath): void;

    public function uploadFile(string $localPath, string $key): void;

    public function deleteFile(string $key): void;

    public function listFiles(string $prefix): array;
}

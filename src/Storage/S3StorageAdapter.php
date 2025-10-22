<?php

declare(strict_types=1);

namespace Keboola\StorageDriver\Sqlite\Storage;

use Aws\S3\S3Client;
use RuntimeException;

class S3StorageAdapter implements CloudStorageInterface
{
    private S3Client $s3Client;
    
    private string $bucket;

    public function __construct(S3Client $s3Client, string $bucket)
    {
        $this->s3Client = $s3Client;
        $this->bucket = $bucket;
    }

    public function fileExists(string $key): bool
    {
        return $this->s3Client->doesObjectExist($this->bucket, $key);
    }

    public function downloadFile(string $key, string $localPath): void
    {
        $localDir = dirname($localPath);
        if (!is_dir($localDir)) {
            mkdir($localDir, 0755, true);
        }

        try {
            $result = $this->s3Client->getObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);

            file_put_contents($localPath, $result['Body']);
        } catch (\Exception $e) {
            throw new RuntimeException(
                sprintf('Failed to download file from S3: %s/%s - %s', $this->bucket, $key, $e->getMessage()),
                0,
                $e
            );
        }
    }

    public function uploadFile(string $localPath, string $key): void
    {
        if (!file_exists($localPath)) {
            throw new RuntimeException(sprintf('Local file not found: %s', $localPath));
        }

        try {
            $this->s3Client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'Body' => fopen($localPath, 'r'),
                'ServerSideEncryption' => 'AES256',
            ]);
        } catch (\Exception $e) {
            throw new RuntimeException(
                sprintf('Failed to upload file to S3: %s/%s - %s', $this->bucket, $key, $e->getMessage()),
                0,
                $e
            );
        }
    }

    public function deleteFile(string $key): void
    {
        try {
            $this->s3Client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);
        } catch (\Exception $e) {
            throw new RuntimeException(
                sprintf('Failed to delete file from S3: %s/%s - %s', $this->bucket, $key, $e->getMessage()),
                0,
                $e
            );
        }
    }

    public function listFiles(string $prefix): array
    {
        try {
            $result = $this->s3Client->listObjectsV2([
                'Bucket' => $this->bucket,
                'Prefix' => $prefix,
            ]);

            $files = [];
            if (isset($result['Contents'])) {
                foreach ($result['Contents'] as $object) {
                    $files[] = $object['Key'];
                }
            }

            return $files;
        } catch (\Exception $e) {
            throw new RuntimeException(
                sprintf('Failed to list files from S3: %s/%s - %s', $this->bucket, $prefix, $e->getMessage()),
                0,
                $e
            );
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use RuntimeException;

final class S3StorageService extends AbstractStorageService
{
    public function __construct(
        private readonly S3Client $client,
        private readonly string $bucket,
        private readonly string $region,
    ) {}

    public function getPublicUrl(string $filePath): string
    {
        return sprintf('https://%s.s3.%s.amazonaws.com/%s', $this->bucket, $this->region, $filePath);
    }

    protected function store(string $sourcePath, string $destinationFilename): void
    {
        try {
            $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $destinationFilename,
                'SourceFile' => $sourcePath,
            ]);
        } catch (AwsException $e) {
            throw new RuntimeException('Failed to upload file to S3: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    protected function fileExists(string $filename): bool
    {
        try {
            $this->client->headObject(['Bucket' => $this->bucket, 'Key' => $filename]);

            return true;
        } catch (AwsException) {
            return false;
        }
    }
}

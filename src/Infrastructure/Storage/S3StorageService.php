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

    protected function createIfAbsent(string $sourcePath, string $destinationFilename): bool
    {
        try {
            $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $destinationFilename,
                'SourceFile' => $sourcePath,
                // S3 refuses the put with 412 when the key exists, so the check and the write are one step
                'IfNoneMatch' => '*',
            ]);

            return true;
        } catch (AwsException $e) {
            if ($e->getStatusCode() === 412) {
                return false;
            }

            throw new RuntimeException('Failed to upload file to S3: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }
}

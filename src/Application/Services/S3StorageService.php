<?php

declare(strict_types=1);

namespace App\Application\Services;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use App\Domain\Interfaces\StorageServiceInterface;
use RuntimeException;

class S3StorageService extends AbstractStorageService implements StorageServiceInterface
{
    private S3Client $s3Client;
    private string $bucket;

    private const CONFIG_PATH = __DIR__ . '/../../../config/aws.php';

    public function __construct()
    {
        parent::__construct();
        $this->initializeS3Client();
    }

    private function initializeS3Client(): void
    {
        $config = require self::CONFIG_PATH;
        $this->s3Client = new S3Client($config);
        $this->bucket = $config['bucket'];
    }

    protected function store(string $sourcePath, string $destinationFilename): void
    {
        try {
            $this->uploadFileToS3($sourcePath, $destinationFilename);
        } catch (AwsException $e) {
            throw new RuntimeException('Failed to upload file to S3: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    private function uploadFileToS3(string $sourcePath, string $destinationFilename): void
    {
        $this->s3Client->putObject([
            'Bucket' => $this->bucket,
            'Key'    => $destinationFilename,
            'SourceFile' => $sourcePath,
        ]);
    }

    protected function fileExists(string $filename): bool
    {
        try {
            return $this->doesFileExistInS3($filename);
        } catch (AwsException $e) {
            return false;
        }
    }

    private function doesFileExistInS3(string $filename): bool
    {
        $this->s3Client->headObject([
            'Bucket' => $this->bucket,
            'Key' => $filename,
        ]);
        return true;
    }

    public function getPublicUrl(string $filePath): string
    {
        return sprintf(
            'https://%s.s3.%s.amazonaws.com/%s',
            getenv('AWS_BUCKET_NAME'),
            getenv('AWS_REGION'),
            $filePath,
        );
    }
}

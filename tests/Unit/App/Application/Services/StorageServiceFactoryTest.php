<?php

declare(strict_types=1);

namespace App\Application\Services;

use Exception;
use PHPUnit\Framework\TestCase;

class StorageServiceFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('AWS_ACCESS_KEY_ID=your_aws_access_key_id');
        putenv('AWS_SECRET_ACCESS_KEY=your_aws_secret_access_key');
        putenv('AWS_REGION=us-east-1');
        putenv('AWS_BUCKET_NAME=your_aws_bucket_name');
        putenv('DROPBOX_ACCESS_TOKEN=your_dropbox_access_token');
    }

    public function testCreateLocalService(): void
    {
        $service = StorageServiceFactory::create('local');
        $this->assertInstanceOf(LocalStorageService::class, $service);
    }

    public function testCreateS3Service(): void
    {
        $service = StorageServiceFactory::create('s3');
        $this->assertInstanceOf(S3StorageService::class, $service);
    }

    public function testCreateDropboxService(): void
    {
        $service = StorageServiceFactory::create('dropbox');
        $this->assertInstanceOf(DropboxStorageService::class, $service);
    }

    public function testCreateUnsupportedService(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unsupported storage service: unsupported');

        StorageServiceFactory::create('unsupported');
    }
}

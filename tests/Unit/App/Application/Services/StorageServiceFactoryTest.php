<?php

declare(strict_types=1);

namespace App\Application\Services;

use Exception;
use PHPUnit\Framework\TestCase;

class StorageServiceFactoryTest extends TestCase
{
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

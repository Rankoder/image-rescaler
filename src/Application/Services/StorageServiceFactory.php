<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Interfaces\StorageServiceInterface;
use InvalidArgumentException;

class StorageServiceFactory
{
    private const SERVICE_DROPBOX = 'dropbox';
    private const SERVICE_S3 = 's3';
    private const SERVICE_LOCAL = 'local';

    public static function create(string $service): StorageServiceInterface
    {
        $normalizedService = strtolower($service);

        return match ($normalizedService) {
            self::SERVICE_DROPBOX => self::createDropboxStorageService(),
            self::SERVICE_S3 => self::createS3StorageService(),
            self::SERVICE_LOCAL => self::createLocalStorageService(),
            default => throw new InvalidArgumentException("Unsupported storage service: {$service}"),
        };
    }

    private static function createDropboxStorageService(): StorageServiceInterface
    {
        return new DropboxStorageService();
    }

    private static function createS3StorageService(): StorageServiceInterface
    {
        return new S3StorageService();
    }

    private static function createLocalStorageService(): StorageServiceInterface
    {
        return new LocalStorageService();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Functional\Infrastructure;

use App\Domain\Enums\StorageType;
use App\Infrastructure\Config\AppConfig;
use App\Infrastructure\Container;
use App\Infrastructure\Storage\DropboxStorageService;
use App\Infrastructure\Storage\LocalStorageService;
use App\Infrastructure\Storage\S3StorageService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Wiring;

final class ContainerTest extends TestCase
{
    /**
     * @return iterable<string, array{StorageType, class-string}>
     */
    public static function storages(): iterable
    {
        yield 'local' => [StorageType::Local, LocalStorageService::class];
        yield 's3' => [StorageType::S3, S3StorageService::class];
        yield 'dropbox' => [StorageType::Dropbox, DropboxStorageService::class];
    }

    /**
     * @param class-string $adapter
     */
    #[DataProvider('storages')]
    public function testRegistersAnAdapterForEveryStorageType(StorageType $type, string $adapter): void
    {
        $this->assertInstanceOf($adapter, Wiring::container()->storageRegistry()->get($type));
    }

    public function testDropboxWithoutATokenFailsOnlyWhenItIsUsed(): void
    {
        $registry = (new Container(AppConfig::fromEnvironment(['DROPBOX_ACCESS_TOKEN' => ''] + getenv(), '/app')))->storageRegistry();

        $this->assertInstanceOf(LocalStorageService::class, $registry->get(StorageType::Local));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Dropbox access token is not set.');
        $registry->get(StorageType::Dropbox);
    }

    public function testDropboxWithAnInvalidDirectoryFailsOnlyWhenItIsUsed(): void
    {
        $registry = (new Container(AppConfig::fromEnvironment(['DROPBOX_DIRECTORY' => 'Apps/thumbnails'] + getenv(), '/app')))->storageRegistry();

        $this->assertInstanceOf(LocalStorageService::class, $registry->get(StorageType::Local));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DROPBOX_DIRECTORY must start with "/", got "Apps/thumbnails".');
        $registry->get(StorageType::Dropbox);
    }
}

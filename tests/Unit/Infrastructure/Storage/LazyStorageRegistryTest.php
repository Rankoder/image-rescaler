<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Storage;

use App\Domain\Enums\StorageType;
use App\Domain\Interfaces\StorageServiceInterface;
use App\Infrastructure\Storage\LazyStorageRegistry;
use App\Infrastructure\Storage\LocalStorageService;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LazyStorageRegistryTest extends TestCase
{
    public function testBuildsOnlyTheStorageThatIsAskedFor(): void
    {
        $local = new LocalStorageService('/tmp', 'http://localhost');
        $registry = new LazyStorageRegistry([
            StorageType::Local->value => static fn(): StorageServiceInterface => $local,
            StorageType::S3->value => static fn(): StorageServiceInterface => throw new RuntimeException('S3 is not configured'),
        ]);

        $this->assertSame($local, $registry->get(StorageType::Local));
    }

    public function testAnUnregisteredStorageIsAProgrammingError(): void
    {
        $this->expectException(LogicException::class);

        (new LazyStorageRegistry([]))->get(StorageType::Dropbox);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Functional\Infrastructure\Storage;

use App\Domain\ValueObjects\ImageValueObject;
use App\Infrastructure\Storage\LocalStorageService;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestFiles;

final class LocalStorageServiceTest extends TestCase
{
    private TestFiles $files;
    private TestFiles $storage;
    private LocalStorageService $service;

    protected function setUp(): void
    {
        $this->files = new TestFiles();
        $this->storage = new TestFiles();
        $this->service = new LocalStorageService($this->storage->directory(), 'http://images.test:8081');
    }

    protected function tearDown(): void
    {
        $this->files->remove();
        $this->storage->remove();
    }

    public function testStoresUnderTheFirstFreeVersionAndRemovesTheTemporaryFile(): void
    {
        $first = $this->files->withContent('first', 'thumb.jpg');
        $this->assertSame('thumb_v1.jpg', $this->service->upload(new ImageValueObject($first, 'thumb.jpg')));
        $this->assertFileDoesNotExist($first);

        $second = $this->files->withContent('second', 'thumb.jpg');
        $this->assertSame('thumb_v2.jpg', $this->service->upload(new ImageValueObject($second, 'thumb.jpg')));

        $this->assertStringEqualsFile($this->storage->directory() . '/thumb_v1.jpg', 'first');
        $this->assertStringEqualsFile($this->storage->directory() . '/thumb_v2.jpg', 'second');
    }

    public function testCreatesTheDirectoryWhenItIsMissing(): void
    {
        $directory = $this->storage->directory() . '/nested';
        $service = new LocalStorageService($directory, 'http://images.test');

        $service->upload(new ImageValueObject($this->files->withContent('x', 'a.png'), 'a.png'));

        $this->assertFileExists($directory . '/a_v1.png');
        unlink($directory . '/a_v1.png');
        rmdir($directory);
    }

    public function testRemovesTheTemporaryFileAlsoWhenStoringFails(): void
    {
        $temporary = $this->files->withContent('image', 'thumb.jpg');
        $blocked = $this->files->withContent('a file where the directory should be', 'blocked');
        $service = new LocalStorageService($blocked . '/storage', 'http://images.test');

        try {
            @$service->upload(new ImageValueObject($temporary, 'thumb.jpg'));
            $this->fail('Storing did not fail.');
        } catch (RuntimeException) {
            $this->assertFileDoesNotExist($temporary);
        }
    }

    public function testAMissingSourceFails(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File not found at path: /nonexistent/thumb.jpg');

        $this->service->upload(new ImageValueObject('/nonexistent/thumb.jpg', 'thumb.jpg'));
    }

    public function testThePublicUrlStartsWithTheConfiguredBase(): void
    {
        $this->assertSame('http://images.test:8081/thumb_v1.jpg', $this->service->getPublicUrl('thumb_v1.jpg'));
    }
}

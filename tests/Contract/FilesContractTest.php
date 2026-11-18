<?php

declare(strict_types=1);

namespace Tests\Contract;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use App\Infrastructure\Controllers\FileController;
use App\Infrastructure\Controllers\NotFoundController;
use Nyholm\Psr7\Factory\Psr17Factory;
use Tests\Support\TestFiles;

final class FilesContractTest extends TestCase
{
    use MatchesContract;

    private TestFiles $storage;
    private FileController $controller;
    private string $stored;

    protected function setUp(): void
    {
        $this->storage = new TestFiles();
        $this->stored = $this->storage->jpeg(60, 40, 'thumb_v1.jpg');
        mkdir($this->storage->directory() . '/input');
        $this->storage->withContent('not served', 'notes.txt');

        $factory = new Psr17Factory();
        $this->controller = new FileController(
            $this->storage->directory(),
            $factory,
            $factory,
            new NotFoundController($factory, $factory),
        );
    }

    protected function tearDown(): void
    {
        rmdir($this->storage->directory() . '/input');
        $this->storage->remove();
    }

    public function testServesAStoredImageWithItsType(): void
    {
        $response = $this->controller->show('thumb_v1.jpg');

        $this->assertSame([200, 'image/jpeg'], [$response->getStatusCode(), $response->getHeaderLine('Content-Type')]);
        $this->assertStringEqualsFile($this->stored, (string) $response->getBody());
        $this->assertMatchesContract('get', '/files/{name}', $response);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function namesNotServed(): iterable
    {
        yield 'missing file' => ['missing.jpg'];
        yield 'input directory' => ['input'];
        yield 'parent directory' => ['../README.md'];
        yield 'other extension' => ['notes.txt'];
    }

    #[DataProvider('namesNotServed')]
    public function testAnythingElseIsNotFound(string $name): void
    {
        $response = $this->controller->show($name);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertMatchesContract('get', '/files/{name}', $response);
    }
}

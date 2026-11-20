<?php

declare(strict_types=1);

namespace Tests\Contract;

use App\Infrastructure\Controllers\FileController;
use App\Infrastructure\Http\HttpKernel;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestFiles;
use Tests\Support\Wiring;

final class FilesContractTest extends TestCase
{
    use MatchesContract;

    private TestFiles $storage;
    private HttpKernel $kernel;
    private string $stored;

    protected function setUp(): void
    {
        $this->storage = new TestFiles();
        $this->stored = $this->storage->jpeg(60, 40, 'thumb_v1.jpg');
        mkdir($this->storage->directory() . '/input');
        $this->storage->withContent('nested', 'input/landscape.jpg');
        $this->storage->withContent('not served', 'notes.txt');
        $this->storage->withContent('partial', '.0123456789abcdef-thumb_v1.jpg');

        $container = Wiring::container();
        $factory = $container->httpFactory();
        $this->kernel = Wiring::httpKernel(
            container: $container,
            fileController: new FileController(
                $this->storage->directory(),
                $factory,
                $factory,
                $container->notFoundController(),
            ),
        );
    }

    protected function tearDown(): void
    {
        $nested = $this->storage->directory() . '/input/landscape.jpg';
        if (is_file($nested)) {
            unlink($nested);
        }
        rmdir($this->storage->directory() . '/input');
        $this->storage->remove();
    }

    public function testServesAStoredImageWithItsType(): void
    {
        $response = $this->kernel->handle(new ServerRequest('GET', '/files/thumb_v1.jpg'));

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
        yield 'encoded traversal' => ['..%2F..%2Fetc%2Fpasswd'];
        yield 'encoded subdirectory' => ['input%2Flandscape.jpg'];
        yield 'dot-dot' => ['..'];
        yield 'dotenv' => ['.env'];
        yield 'null byte' => ['thumb.jpg%00.png'];
        yield 'encoded trailing newline' => ['thumb.jpg%0A'];
        yield 'raw trailing newline' => ["thumb_v1.jpg\n"];
        yield 'over 200 characters' => [str_repeat('a', 201) . '.jpg'];
        yield 'other extension' => ['notes.txt'];
        yield 'partial file' => ['.0123456789abcdef-thumb_v1.jpg'];
    }

    #[DataProvider('namesNotServed')]
    public function testAnythingElseIsNotFound(string $name): void
    {
        $response = $this->kernel->handle(new ServerRequest('GET', '/files/' . $name));

        $this->assertErrors(404, [[null, 'not_found']], 'get', '/files/{name}', $response);
    }
}

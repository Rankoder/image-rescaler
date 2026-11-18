<?php

declare(strict_types=1);

namespace Tests\Contract;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Wiring;

final class FilesContractTest extends TestCase
{
    use MatchesContract;

    private const STORAGE_DIR = __DIR__ . '/../../storage/';

    private string $name;

    protected function setUp(): void
    {
        $this->name = uniqid('files_test_') . '.jpg';
        copy(__DIR__ . '/../fixtures/test_image.jpg', self::STORAGE_DIR . $this->name);
    }

    protected function tearDown(): void
    {
        unlink(self::STORAGE_DIR . $this->name);
    }

    public function testServesAStoredImageWithItsType(): void
    {
        $response = Wiring::container()->fileController()->show($this->name);

        $this->assertSame([200, 'image/jpeg'], [$response->getStatusCode(), $response->getHeaderLine('Content-Type')]);
        $this->assertStringEqualsFile(self::STORAGE_DIR . $this->name, (string) $response->getBody());
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
        yield 'other extension' => ['.gitignore'];
    }

    #[DataProvider('namesNotServed')]
    public function testAnythingElseIsNotFound(string $name): void
    {
        $response = Wiring::container()->fileController()->show($name);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertMatchesContract('get', '/files/{name}', $response);
    }
}

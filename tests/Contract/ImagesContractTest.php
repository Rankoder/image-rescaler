<?php

declare(strict_types=1);

namespace Tests\Contract;

use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\TestFiles;

/**
 * The input directory over HTTP, sent through routes.php with its own INPUT_DIR; every response checked against openapi.yaml.
 */
final class ImagesContractTest extends TestCase
{
    use MatchesContract;

    private const STORAGE_DIR = __DIR__ . '/../../storage/';

    private TestFiles $input;
    private TestFiles $files;
    private string $outputName;

    protected function setUp(): void
    {
        $this->input = new TestFiles();
        $this->files = new TestFiles();
        $this->outputName = uniqid('images_test_');
        $this->input->jpeg(300, 200, 'landscape.jpg');
        $this->input->png(200, 300, 'portrait.png');
        putenv('INPUT_DIR=' . $this->input->directory());
    }

    protected function tearDown(): void
    {
        putenv('INPUT_DIR');
        $this->input->remove();
        $this->files->remove();
        foreach (glob(self::STORAGE_DIR . $this->outputName . '*') ?: [] as $stored) {
            unlink($stored);
        }
    }

    #[RunInSeparateProcess]
    public function testListsTheImagesInTheInputDirectory(): void
    {
        $this->input->withContent('partial', '.0123-hidden.jpg');

        $response = $this->dispatch('GET', '/images');

        $this->assertMatchesContract('get', '/images', $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['images' => ['landscape.jpg', 'portrait.png']], $this->json($response));
    }

    #[RunInSeparateProcess]
    public function testAddsAnImageUnderTheGivenNameWithTheExtensionOfItsType(): void
    {
        $response = $this->addImage($this->files->webp(400, 400, 'upload.webp'), 'upload.webp', ['name' => 'holiday']);

        $this->assertMatchesContract('post', '/images', $response);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(['status' => 'success', 'name' => 'holiday.webp'], $this->json($response));
        $this->assertFileEquals($this->files->directory() . '/upload.webp', $this->input->directory() . '/holiday.webp');
    }

    #[RunInSeparateProcess]
    public function testWithoutANameTheFileKeepsASafeVersionOfItsOwn(): void
    {
        $response = $this->addImage($this->files->png(100, 100, 'source.png'), 'my photo.png');

        $this->assertSame(['status' => 'success', 'name' => 'my_photo.png'], $this->json($response));
    }

    #[RunInSeparateProcess]
    public function testATakenNameIsRejectedAndTheImageIsKept(): void
    {
        $original = (string) file_get_contents($this->input->directory() . '/landscape.jpg');

        $response = $this->addImage($this->files->jpeg(100, 100, 'other.jpg'), 'other.jpg', ['name' => 'landscape']);

        $this->assertErrors(422, [['name', 'already_exists']], 'post', '/images', $response);
        $this->assertSame($original, file_get_contents($this->input->directory() . '/landscape.jpg'));
    }

    /**
     * @return iterable<string, array{string, string, array<string, string>, list<array{string, string}>}>
     */
    public static function rejectedAdditions(): iterable
    {
        yield 'not an image' => ['text', 'notes.jpg', [], [['file', 'unsupported_type']]];
        yield 'extension of another type' => ['png', 'photo.jpg', [], [['file', 'extension_mismatch']]];
        yield 'name with a path' => ['png', 'photo.png', ['name' => '../photo'], [['name', 'invalid_characters']]];
        yield 'name too long' => ['png', 'photo.png', ['name' => str_repeat('a', 101)], [['name', 'too_long']]];
        yield 'no file' => ['none', '', [], [['file', 'required']]];
    }

    /**
     * @param 'text'|'png'|'none' $kind
     * @param array<string, string> $fields
     * @param list<array{string, string}> $errors
     */
    #[DataProvider('rejectedAdditions')]
    #[RunInSeparateProcess]
    public function testRejectsAnAddition(string $kind, string $clientFilename, array $fields, array $errors): void
    {
        $source = match ($kind) {
            'text' => $this->files->withContent('not an image', 'source'),
            'png' => $this->files->png(100, 100, 'source.png'),
            'none' => null,
        };

        $response = $source === null
            ? $this->dispatch('POST', '/images', fields: $fields)
            : $this->addImage($source, $clientFilename, $fields);

        $this->assertErrors(422, $errors, 'post', '/images', $response);
        $this->assertSame(['landscape.jpg', 'portrait.png'], $this->listing());
    }

    #[RunInSeparateProcess]
    public function testAnAdditionThatIsNotMultipartIsMalformed(): void
    {
        $response = $this->dispatch('POST', '/images', contentType: 'application/json');

        $this->assertErrors(400, [[null, 'malformed_request']], 'post', '/images', $response);
    }

    #[RunInSeparateProcess]
    public function testUploadCropsAnImageFromTheInputDirectory(): void
    {
        $response = $this->dispatch('POST', '/upload', fields: [
            'image' => 'portrait.png',
            'max_width' => '60',
            'max_height' => '40',
            'output_filename' => $this->outputName,
        ]);

        $this->assertMatchesContract('post', '/upload', $response);
        $this->assertSame(200, $response->getStatusCode());
        $size = getimagesize(self::STORAGE_DIR . $this->outputName . '_v1.png');
        $this->assertNotFalse($size);
        $this->assertSame([60, 40], [$size[0], $size[1]]);
        $this->assertFileExists($this->input->directory() . '/portrait.png');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function imagesNotFound(): iterable
    {
        yield 'missing' => ['missing.jpg'];
        yield 'outside the directory' => ['../../README.md'];
        yield 'absolute path' => ['/etc/passwd'];
        yield 'hidden partial file' => ['.0123-hidden.jpg'];
    }

    #[DataProvider('imagesNotFound')]
    #[RunInSeparateProcess]
    public function testUploadRejectsAnImageThatIsNotInTheDirectory(string $image): void
    {
        $this->input->withContent('partial', '.0123-hidden.jpg');

        $response = $this->dispatch('POST', '/upload', fields: ['image' => $image, 'max_width' => '60', 'max_height' => '40']);

        $this->assertErrors(422, [['image', 'not_found']], 'post', '/upload', $response);
    }

    #[RunInSeparateProcess]
    public function testUploadAppliesTheFileRulesToAnImageFromTheDirectory(): void
    {
        $response = $this->dispatch('POST', '/upload', fields: ['image' => 'landscape.jpg', 'max_width' => '400', 'max_height' => '40']);

        $this->assertErrors(422, [['image', 'smaller_than_frame']], 'post', '/upload', $response);
    }

    #[RunInSeparateProcess]
    public function testUploadTakesAFileOrAnImageButNotBoth(): void
    {
        $source = $this->files->jpeg(150, 150, 'photo.jpg');

        $response = $this->dispatch('POST', '/upload', files: $this->uploaded($source, 'photo.jpg'), fields: [
            'image' => 'landscape.jpg',
            'max_width' => '60',
            'max_height' => '40',
        ]);

        $this->assertErrors(422, [['image', 'conflicts_with_file']], 'post', '/upload', $response);
    }

    #[RunInSeparateProcess]
    public function testTheFileOrImageConflictComesWithTheOtherErrors(): void
    {
        $source = $this->files->jpeg(150, 150, 'photo.jpg');

        $response = $this->dispatch('POST', '/upload', files: $this->uploaded($source, 'photo.jpg'), fields: [
            'image' => 'landscape.jpg',
            'max_width' => '39',
            'max_height' => '40',
            'service' => 'ftp',
        ]);

        $this->assertErrors(422, [['image', 'conflicts_with_file'], ['max_width', 'out_of_range'], ['service', 'unsupported']], 'post', '/upload', $response);
    }

    /**
     * @param array<string, string> $fields
     */
    private function addImage(string $source, string $clientFilename, array $fields = []): ResponseInterface
    {
        return $this->dispatch('POST', '/images', files: $this->uploaded($source, $clientFilename), fields: $fields);
    }

    /**
     * @return array<string, array<string, int|string>>
     */
    private function uploaded(string $source, string $clientFilename): array
    {
        return ['file' => [
            'name' => $clientFilename,
            'type' => 'application/octet-stream',
            'tmp_name' => $source,
            'error' => UPLOAD_ERR_OK,
            'size' => (int) filesize($source),
        ]];
    }

    /**
     * @param array<string, array<string, int|string>> $files
     * @param array<string, string> $fields
     */
    private function dispatch(
        string $method,
        string $uri,
        array $files = [],
        array $fields = [],
        string $contentType = 'multipart/form-data; boundary=contract',
    ): ResponseInterface {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['CONTENT_TYPE'] = $contentType;
        $_POST = $fields;
        $_FILES = $files;

        ob_start();
        try {
            require __DIR__ . '/../../routes.php';
        } finally {
            $body = (string) ob_get_clean();
        }

        $status = http_response_code();

        return new Response(is_int($status) ? $status : 200, ['Content-Type' => 'application/json'], $body);
    }

    /**
     * @param list<array{?string, string}> $expected field and code of every error
     */
    private function assertErrors(int $status, array $expected, string $method, string $path, ResponseInterface $response): void
    {
        $this->assertMatchesContract($method, $path, $response);
        $this->assertSame($status, $response->getStatusCode());

        $errors = $this->json($response)['errors'] ?? null;
        $this->assertIsArray($errors);
        $actual = [];
        foreach ($errors as $error) {
            $this->assertIsArray($error);
            $actual[] = [$error['field'] ?? null, $error['code'] ?? null];
        }
        $this->assertEqualsCanonicalizing($expected, $actual);
    }

    /**
     * @return array<mixed>
     */
    private function json(ResponseInterface $response): array
    {
        $decoded = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @return list<string>
     */
    private function listing(): array
    {
        return array_values(array_filter(
            scandir($this->input->directory()) ?: [],
            static fn(string $name): bool => !str_starts_with($name, '.'),
        ));
    }
}

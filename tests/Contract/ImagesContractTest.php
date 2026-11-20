<?php

declare(strict_types=1);

namespace Tests\Contract;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UploadedFileInterface;
use Tests\Support\StoredFiles;
use Tests\Support\TestFiles;
use Tests\Support\Wiring;

/**
 * The input directory over HTTP, sent through HttpKernel with its own INPUT_DIR; every response checked against openapi.yaml.
 */
final class ImagesContractTest extends TestCase
{
    use MatchesContract;

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
        StoredFiles::remove($this->outputName);
    }

    public function testListsTheImagesInTheInputDirectory(): void
    {
        $this->input->withContent('partial', '.0123-hidden.jpg');

        $response = $this->dispatch('GET', '/images');

        $this->assertMatchesContract('get', '/images', $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['images' => ['landscape.jpg', 'portrait.png']], $this->json($response));
    }

    public function testAddsAnImageUnderTheGivenNameWithTheExtensionOfItsType(): void
    {
        $response = $this->addImage($this->files->webp(400, 400, 'upload.webp'), 'upload.webp', ['name' => 'holiday']);

        $this->assertMatchesContract('post', '/images', $response);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(['status' => 'success', 'name' => 'holiday.webp'], $this->json($response));
        $stored = $this->input->directory() . '/holiday.webp';
        $this->assertFileExists($stored);
        $size = getimagesize($stored);
        $this->assertNotFalse($size);
        $this->assertSame([400, 400], [$size[0], $size[1]]);
        $this->assertSame('image/webp', $size['mime']);
    }

    public function testReencodesUploadedImageAndStripsExifAndGpsMetadata(): void
    {
        $source = $this->files->jpegWithExif(200, 150, 'camera.jpg');
        $response = $this->addImage($source, 'camera.jpg', ['name' => 'clean']);

        $this->assertMatchesContract('post', '/images', $response);
        $this->assertSame(201, $response->getStatusCode());
        $stored = $this->input->directory() . '/clean.jpg';
        $this->assertFileExists($stored);
        $size = getimagesize($stored);
        $this->assertNotFalse($size);
        $this->assertSame('image/jpeg', $size['mime']);
        $bytes = (string) file_get_contents($stored);
        $this->assertStringNotContainsString("Exif\x00\x00", $bytes);
        $this->assertStringNotContainsString('GPSLatitude', $bytes);
    }

    public function testWithoutANameTheFileKeepsASafeVersionOfItsOwn(): void
    {
        $response = $this->addImage($this->files->png(100, 100, 'source.png'), 'my photo.png');

        $this->assertSame(['status' => 'success', 'name' => 'my_photo.png'], $this->json($response));
    }

    public function testATakenNameIsRejectedAndTheImageIsKept(): void
    {
        $original = (string) file_get_contents($this->input->directory() . '/landscape.jpg');

        $response = $this->addImage($this->files->jpeg(100, 100, 'other.jpg'), 'other.jpg', ['name' => 'landscape']);

        $this->assertErrors(422, [['name', 'already_exists']], 'post', '/images', $response);
        $this->assertSame($original, file_get_contents($this->input->directory() . '/landscape.jpg'));
    }

    public function testRejectsAnAdditionWhenTheInputDirectoryHoldsTenImages(): void
    {
        for ($i = 3; $i <= 10; $i++) {
            $this->input->jpeg(100, 100, "extra_{$i}.jpg");
        }

        $response = $this->addImage($this->files->jpeg(100, 100, 'eleventh.jpg'), 'eleventh.jpg', ['name' => 'eleventh']);

        $this->assertErrors(422, [[null, 'limit_reached']], 'post', '/images', $response);
        $this->assertCount(10, $this->listing());
    }

    public function testLimitReachedComesTogetherWithFieldErrors(): void
    {
        for ($i = 3; $i <= 10; $i++) {
            $this->input->jpeg(100, 100, "extra_{$i}.jpg");
        }

        $response = $this->addImage($this->files->jpeg(100, 100, 'eleventh.jpg'), 'eleventh.jpg', ['name' => '../bad']);

        $this->assertErrors(422, [[null, 'limit_reached'], ['name', 'invalid_characters']], 'post', '/images', $response);
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

    public function testAnAdditionThatIsNotMultipartIsMalformed(): void
    {
        $response = $this->dispatch('POST', '/images', contentType: 'application/json');

        $this->assertErrors(400, [[null, 'malformed_request']], 'post', '/images', $response);
    }

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
        $size = getimagesize(StoredFiles::path($this->outputName . '_v1.png'));
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
    public function testUploadRejectsAnImageThatIsNotInTheDirectory(string $image): void
    {
        $this->input->withContent('partial', '.0123-hidden.jpg');

        $response = $this->dispatch('POST', '/upload', fields: ['image' => $image, 'max_width' => '60', 'max_height' => '40']);

        $this->assertErrors(422, [['image', 'not_found']], 'post', '/upload', $response);
    }

    public function testUploadAppliesTheFileRulesToAnImageFromTheDirectory(): void
    {
        $response = $this->dispatch('POST', '/upload', fields: ['image' => 'landscape.jpg', 'max_width' => '400', 'max_height' => '40']);

        $this->assertErrors(422, [['image', 'smaller_than_frame']], 'post', '/upload', $response);
    }

    public function testUploadTakesAFileOrAnImageButNotBoth(): void
    {
        $source = $this->files->jpeg(150, 150, 'photo.jpg');

        $response = $this->dispatch('POST', '/upload', files: ['file' => $this->uploadedFile($source, 'photo.jpg')], fields: [
            'image' => 'landscape.jpg',
            'max_width' => '60',
            'max_height' => '40',
        ]);

        $this->assertErrors(422, [['image', 'conflicts_with_file']], 'post', '/upload', $response);
    }

    public function testTheFileOrImageConflictComesWithTheOtherErrors(): void
    {
        $source = $this->files->jpeg(150, 150, 'photo.jpg');

        $response = $this->dispatch('POST', '/upload', files: ['file' => $this->uploadedFile($source, 'photo.jpg')], fields: [
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
        return $this->dispatch('POST', '/images', files: ['file' => $this->uploadedFile($source, $clientFilename)], fields: $fields);
    }

    /**
     * @param array<string, UploadedFileInterface> $files
     * @param array<string, string> $fields
     */
    private function dispatch(
        string $method,
        string $uri,
        array $files = [],
        array $fields = [],
        string $contentType = 'multipart/form-data; boundary=contract',
    ): ResponseInterface {
        $request = $method === 'POST'
            ? $this->multipartRequest($uri, $files, $fields, $contentType)
            : new ServerRequest($method, $uri);

        return Wiring::httpKernel()->handle($request);
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

<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\Wiring;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class UploadToLocalStorageTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../fixtures/test_image.jpg';
    private const STORAGE_DIR = __DIR__ . '/../../storage/';

    private string $outputName;
    private string $uploadedFilePath;

    protected function setUp(): void
    {
        $this->outputName = uniqid('integration_test_');
        $this->uploadedFilePath = sys_get_temp_dir() . '/' . $this->outputName . '_upload';
        copy(self::FIXTURE, $this->uploadedFilePath);
    }

    protected function tearDown(): void
    {
        $leftovers = [
            $this->uploadedFilePath,
            ...$this->storedFiles(),
            ...(glob(sys_get_temp_dir() . '/' . $this->outputName . '*') ?: []),
        ];

        foreach ($leftovers as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function testStoresTheImageCroppedToExactlyTheRequestedBox(): void
    {
        $response = $this->upload('photo.jpg', 60, 40);

        $storedName = $this->outputName . '_v1.jpg';
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            ['status' => 'success', 'path' => $storedName, 'url' => 'http://localhost:8080/' . $storedName],
            $this->json($response),
        );

        $size = getimagesize(self::STORAGE_DIR . $storedName);
        $this->assertNotFalse($size);
        $this->assertSame([60, 40, IMAGETYPE_JPEG], [$size[0], $size[1], $size[2]]);
    }

    public function testRejectsAnImageSmallerThanTheRequestedBoxInsteadOfEnlargingIt(): void
    {
        $response = $this->upload('photo.jpg', 600, 400);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('smaller_than_frame', $this->firstError($response)['code']);
        $this->assertSame([], $this->storedFiles());
    }

    public function testASecondUploadUnderTheSameNameGetsTheNextVersion(): void
    {
        $this->upload('photo.jpg', 60, 40);
        copy(self::FIXTURE, $this->uploadedFilePath);

        $response = $this->upload('photo.jpg', 60, 40);

        $this->assertSame($this->outputName . '_v2.jpg', $this->json($response)['path']);
        $this->assertSame(
            [self::STORAGE_DIR . $this->outputName . '_v1.jpg', self::STORAGE_DIR . $this->outputName . '_v2.jpg'],
            $this->storedFiles(),
        );
    }

    public function testRejectsAFileWhoseNameDoesNotMatchItsContent(): void
    {
        $response = $this->upload('photo.gif', 60, 40);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(
            ['field' => 'file', 'code' => 'extension_mismatch', 'message' => 'The file is a JPEG image, so its name must end with .jpg or .jpeg.'],
            $this->firstError($response),
        );
        $this->assertSame([], $this->storedFiles());
    }

    private function upload(string $clientFilename, int $maxWidth, int $maxHeight): ResponseInterface
    {
        $factory = new Psr17Factory();
        $uploadedFile = $factory->createUploadedFile(
            $factory->createStreamFromFile($this->uploadedFilePath),
            clientFilename: $clientFilename,
        );

        $request = (new ServerRequest('POST', '/upload', ['Content-Type' => 'multipart/form-data; boundary=test']))
            ->withUploadedFiles(['file' => $uploadedFile])
            ->withParsedBody([
                'service' => 'local',
                'max_width' => (string) $maxWidth,
                'max_height' => (string) $maxHeight,
                'output_filename' => $this->outputName,
            ]);

        return Wiring::imageController()->upload($request);
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
     * @return array<mixed>
     */
    private function firstError(ResponseInterface $response): array
    {
        $errors = $this->json($response)['errors'] ?? null;
        $this->assertIsArray($errors);
        $this->assertIsArray($errors[0] ?? null);

        return $errors[0];
    }

    /**
     * @return list<string>
     */
    private function storedFiles(): array
    {
        return glob(self::STORAGE_DIR . $this->outputName . '*') ?: [];
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\ValueObjects\ImageValueObject;
use Kunnu\Dropbox\Dropbox;
use Kunnu\Dropbox\Exceptions\DropboxClientException;
use Kunnu\Dropbox\Models\FileMetadata;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Mockery;

class DropboxStorageServiceTest extends TestCase
{
    private DropboxStorageService $service;
    private $dropboxMock;

    protected function setUp(): void
    {
        // Mock the environment variables
        $_ENV['DROPBOX_ACCESS_TOKEN'] = 'mocked_dropbox_access_token';

        // Create a Dropbox mock
        $this->dropboxMock = Mockery::mock(Dropbox::class);
        $this->service = $this->createServiceWithMockedDropbox();
    }

    private function createServiceWithMockedDropbox(): DropboxStorageService
    {
        $service = new DropboxStorageService();
        $reflection = new \ReflectionClass($service);
        $dropboxProperty = $reflection->getProperty('dropbox');
        $dropboxProperty->setValue($service, $this->dropboxMock);

        return $service;
    }

    #[DataProvider('uploadProvider')]
    public function testUpload(string $filename, array $metadata, string $expected, array $existingMetadata): void
    {
        $image = $this->createTestImage($filename);

        $this->dropboxMock->shouldReceive('upload')
            ->once()
            ->andReturn(new FileMetadata($metadata));

        foreach ($existingMetadata as $existingFile => $exists) {
            $mock = $this->dropboxMock->shouldReceive('getMetadata')
                ->with('/Aplikacje/image-rescaler/' . $existingFile);
            if ($exists) {
                $mock->andReturn(new FileMetadata([]));
            } else {
                $mock->andThrow(new DropboxClientException('File not found'));
            }
        }

        $result = $this->service->upload($image);
        $this->assertSame($expected, $result);

        $this->cleanupTestImage($image->getPath());
    }

    public static function uploadProvider(): array
    {
        return [
            'first upload' => [
                'test_image.jpg',
                ['path_display' => '/Aplikacje/image-rescaler/test_image_v1.jpg'],
                'test_image_v1.jpg',
                ['test_image_v1.jpg' => false]
            ],
            'second upload' => [
                'test_image.jpg',
                ['path_display' => '/Aplikacje/image-rescaler/test_image_v2.jpg'],
                'test_image_v2.jpg',
                ['test_image_v1.jpg' => true, 'test_image_v2.jpg' => false]
            ],
        ];
    }

    private function createTestImage(string $filename): ImageValueObject
    {
        $imagePath = sys_get_temp_dir() . '/' . $filename;
        file_put_contents($imagePath, 'test content');
        return new ImageValueObject($imagePath, $filename);
    }

    private function cleanupTestImage(string $imagePath): void
    {
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}

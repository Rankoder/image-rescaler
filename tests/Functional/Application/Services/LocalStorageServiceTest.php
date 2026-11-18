<?php

declare(strict_types=1);

namespace Tests\Functional\Application\Services;

use App\Application\Services\LocalStorageService;
use App\Domain\ValueObjects\ImageValueObject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class LocalStorageServiceTest extends TestCase
{
    private LocalStorageService $service;
    private string $mockStoragePath;

    protected function setUp(): void
    {
        $this->service = new LocalStorageService();
        $this->mockStoragePath = sys_get_temp_dir() . '/mock_storage/';

        // Zamockuj właściwość storagePath za pomocą refleksji
        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('storagePath');
        $property->setValue($this->service, $this->mockStoragePath);

        // Upewnij się, że katalog storage istnieje
        if (!file_exists($this->mockStoragePath)) {
            mkdir($this->mockStoragePath, 0777, true);
        }
    }

    #[DataProvider('imageProvider')]
    public function testStoreAndFileExists(string $filename, string $expectedStoredFilename): void
    {
        $image = $this->createTestImage($filename);

        $storedFilename = $this->service->upload($image);
        $this->assertSame($expectedStoredFilename, $storedFilename);

        $storedFilePath = $this->mockStoragePath . $expectedStoredFilename;
        $this->assertFileExists($storedFilePath);
    }

    public function testStoreFileNotFound(): void
    {
        $nonExistingFilePath = '/path/to/non_existing_file.jpg';
        $image = new ImageValueObject($nonExistingFilePath, 'non_existing_file.jpg');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("File not found at path: {$nonExistingFilePath}");

        $this->service->upload($image);
    }

    public static function imageProvider(): array
    {
        return [
            'first upload' => ['test_image.jpg', 'test_image_v1.jpg'],
            'second upload' => ['test_image55.jpg', 'test_image55_v1.jpg'],
        ];
    }

    private function createTestImage(string $filename): ImageValueObject
    {
        $imagePath = sys_get_temp_dir() . '/' . $filename;
        file_put_contents($imagePath, 'test content');
        return new ImageValueObject($imagePath, $filename);
    }

    private function cleanupTestFiles(array $filePaths): void
    {
        foreach ($filePaths as $filePath) {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->mockStoragePath)) {
            array_map('unlink', glob("$this->mockStoragePath/*.*"));
            rmdir($this->mockStoragePath);
        }
    }
}

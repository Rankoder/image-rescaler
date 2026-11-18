<?php

declare(strict_types=1);

namespace Tests\Functional\Application\Services;

use App\Application\Services\ImageProcessingService;
use App\Application\Validation\ResizeRequest;
use App\Domain\Enums\ImageFormat;
use App\Domain\Enums\StorageType;
use App\Domain\ValueObjects\ImageValueObject;
use PHPUnit\Framework\TestCase;

class ImageProcessingServiceResizeImageTest extends TestCase
{
    private ImageProcessingService $service;
    private string $testImagePath;
    private string $outputFilename;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->service = new ImageProcessingService();
        $this->tempDir = sys_get_temp_dir();
        $this->testImagePath = $this->tempDir . '/test_image.jpg';
        $this->outputFilename = 'resized_test_image.jpg';

        $this->createTestImage();
    }

    private function createTestImage(): void
    {
        $image = imagecreatetruecolor(100, 100);
        $backgroundColor = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $backgroundColor);
        imagejpeg($image, $this->testImagePath, 90);
    }

    public function testResizeImage(): void
    {
        $maxWidth = 50;
        $maxHeight = 50;
        $expectedTempFilePath = $this->tempDir . '/' . $this->outputFilename;
        $request = new ResizeRequest($this->testImagePath, 'test_image.jpg', ImageFormat::Jpeg, $maxWidth, $maxHeight, StorageType::Local, null);

        $result = $this->service->resizeImage($request, $this->outputFilename);

        $this->assertInstanceOf(ImageValueObject::class, $result);
        $this->assertSame($expectedTempFilePath, $result->getPath());
        $this->assertSame($this->outputFilename, $result->getFilename());

        list($width, $height) = getimagesize($result->getPath());
        $this->assertSame($maxWidth, $width);
        $this->assertSame($maxHeight, $height);

        $this->cleanupTestFiles([$this->testImagePath, $result->getPath()]);
    }

    protected function tearDown(): void
    {
        $this->cleanupTestFiles([$this->testImagePath, $this->tempDir . '/' . $this->outputFilename]);
    }

    private function cleanupTestFiles(array $filePaths): void
    {
        foreach ($filePaths as $filePath) {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }
}

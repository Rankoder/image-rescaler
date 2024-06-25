<?php

declare(strict_types=1);

namespace App\Application\Services;

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
        imagedestroy($image);
    }

    public function testResizeImage(): void
    {
        $file = ['tmp_name' => $this->testImagePath];
        $maxWidth = 50;
        $maxHeight = 50;
        $expectedTempFilePath = $this->tempDir . '/' . $this->outputFilename;

        $result = $this->service->resizeImage($file, $maxWidth, $maxHeight, $this->outputFilename);

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

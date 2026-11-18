<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Services;

use App\Application\Services\ImageProcessingService;
use App\Application\Validation\ResizeRequest;
use App\Domain\Enums\ImageFormat;
use App\Domain\Enums\StorageType;
use PHPUnit\Framework\TestCase;

final class ImageProcessingServiceOutputFilenameTest extends TestCase
{
    public function testUsesTheRequestedNameWithTheExtensionOfTheFormat(): void
    {
        $this->assertSame('thumbnail.webp', $this->outputFilename('photo.webp', ImageFormat::Webp, 'thumbnail'));
    }

    public function testTheJpegExtensionIsAlwaysJpg(): void
    {
        $this->assertSame('thumbnail.jpg', $this->outputFilename('photo.jpeg', ImageFormat::Jpeg, 'thumbnail'));
    }

    public function testWithoutARequestedNameGeneratesOneFromTheSourceName(): void
    {
        $this->assertMatchesRegularExpression(
            '/^resized_photo_[a-z0-9]{13}\.png$/',
            $this->outputFilename('photo.png', ImageFormat::Png, null),
        );
    }

    public function testTheGeneratedNameKeepsOnlySafeCharactersOfTheSourceName(): void
    {
        $this->assertMatchesRegularExpression(
            '/^resized_my_photo__1__[a-z0-9]{13}\.jpg$/',
            $this->outputFilename('../my photo (1).jpg', ImageFormat::Jpeg, null),
        );
    }

    private function outputFilename(string $sourceName, ImageFormat $format, ?string $outputFilename): string
    {
        $request = new ResizeRequest('/tmp/source', $sourceName, $format, 60, 40, StorageType::Local, $outputFilename);

        return (new ImageProcessingService())->outputFilename($request);
    }
}

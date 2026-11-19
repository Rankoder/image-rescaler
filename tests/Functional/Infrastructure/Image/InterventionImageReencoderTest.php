<?php

declare(strict_types=1);

namespace Tests\Functional\Infrastructure\Image;

use App\Domain\Enums\ImageFormat;
use App\Infrastructure\Image\InterventionImageReencoder;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestFiles;

final class InterventionImageReencoderTest extends TestCase
{
    private TestFiles $files;
    private InterventionImageReencoder $reencoder;

    protected function setUp(): void
    {
        $this->files = new TestFiles();
        $this->reencoder = new InterventionImageReencoder(new ImageManager(new GdDriver()), $this->files->directory());
    }

    protected function tearDown(): void
    {
        $this->files->remove();
    }

    public function testReencodesJpegAndDropsExifAndGpsMetadata(): void
    {
        $source = $this->files->jpegWithExif(120, 80);
        $this->assertStringContainsString("Exif\x00\x00", (string) file_get_contents($source));
        $this->assertStringContainsString('GPSLatitude', (string) file_get_contents($source));

        $result = $this->reencoder->reencode($source, ImageFormat::Jpeg);

        $size = getimagesize($result);
        $this->assertNotFalse($size);
        $this->assertSame(120, $size[0]);
        $this->assertSame(80, $size[1]);
        $this->assertSame('image/jpeg', $size['mime']);
        $bytes = (string) file_get_contents($result);
        $this->assertStringNotContainsString("Exif\x00\x00", $bytes);
        $this->assertStringNotContainsString('GPSLatitude', $bytes);
    }

    public function testPreservesPngAndWebpFormats(): void
    {
        $png = $this->reencoder->reencode($this->files->png(60, 40), ImageFormat::Png);
        $webp = $this->reencoder->reencode($this->files->webp(60, 40), ImageFormat::Webp);

        $pngInfo = getimagesize($png);
        $webpInfo = getimagesize($webp);
        $this->assertNotFalse($pngInfo);
        $this->assertNotFalse($webpInfo);
        $this->assertSame('image/png', $pngInfo['mime']);
        $this->assertSame('image/webp', $webpInfo['mime']);
    }
}

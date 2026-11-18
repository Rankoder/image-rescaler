<?php

declare(strict_types=1);

namespace Tests\Functional\Infrastructure\Image;

use App\Domain\Enums\ImageFormat;
use App\Infrastructure\Image\InterventionImageResizer;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestFiles;

final class InterventionImageResizerTest extends TestCase
{
    private TestFiles $files;
    private string $outputFilename;

    protected function setUp(): void
    {
        $this->files = new TestFiles();
        $this->outputFilename = uniqid('resize_test_');
    }

    protected function tearDown(): void
    {
        $this->files->remove();
        foreach (glob(sys_get_temp_dir() . '/' . $this->outputFilename . '*') ?: [] as $path) {
            unlink($path);
        }
    }

    /**
     * @return iterable<string, array{ImageFormat, int}>
     */
    public static function formats(): iterable
    {
        yield 'JPEG' => [ImageFormat::Jpeg, IMAGETYPE_JPEG];
        yield 'PNG' => [ImageFormat::Png, IMAGETYPE_PNG];
        yield 'WebP' => [ImageFormat::Webp, IMAGETYPE_WEBP];
    }

    #[DataProvider('formats')]
    public function testFillsTheFrameExactlyInTheFormatOfTheSource(ImageFormat $format, int $imageType): void
    {
        $source = match ($format) {
            ImageFormat::Jpeg => $this->files->jpeg(150, 100),
            ImageFormat::Png => $this->files->png(150, 100),
            ImageFormat::Webp => $this->files->webp(150, 100),
        };

        $image = $this->resize($source, $format, 40, 60);

        $size = getimagesize($image);
        $this->assertNotFalse($size);
        $this->assertSame([40, 60, $imageType], [$size[0], $size[1], $size[2]]);
    }

    public function testCutsTheOverflowEvenlyFromBothSides(): void
    {
        // 300 × 100, left half red, right half blue; scaled to 120 × 40, then 40 px cut from each side
        $image = imagecreatefrompng($this->resize($this->files->png(300, 100), ImageFormat::Png, 40, 40));
        $this->assertNotFalse($image);

        $this->assertSame([255, 0, 0], $this->rgb($image, 5, 20));
        $this->assertSame([0, 0, 255], $this->rgb($image, 34, 20));
    }

    public function testEveryCallWritesItsOwnTemporaryFileEvenUnderTheSameName(): void
    {
        $source = $this->files->png(100, 100);
        $resizer = new InterventionImageResizer(new ImageManager(new GdDriver()), $this->files->directory());

        $first = $resizer->cover($source, 40, 40, 'thumb.png');
        $second = $resizer->cover($source, 40, 40, 'thumb.png');

        $this->assertNotSame($first->getPath(), $second->getPath());
        $this->assertSame(['thumb.png', 'thumb.png'], [$first->getFilename(), $second->getFilename()]);
        $this->assertStringEndsWith('-thumb.png', $first->getPath());
        $this->assertFileExists($first->getPath());
        $this->assertFileExists($second->getPath());
    }

    private function resize(string $source, ImageFormat $format, int $width, int $height): string
    {
        $filename = $this->outputFilename . '.' . $format->extension();
        $resizer = new InterventionImageResizer(new ImageManager(new GdDriver()), sys_get_temp_dir());

        $result = $resizer->cover($source, $width, $height, $filename);

        $this->assertSame($filename, $result->getFilename());

        return $result->getPath();
    }

    /**
     * @return array{int, int, int}
     */
    private function rgb(\GdImage $image, int $x, int $y): array
    {
        $color = (int) imagecolorat($image, $x, $y);

        return [($color >> 16) & 0xFF, ($color >> 8) & 0xFF, $color & 0xFF];
    }
}

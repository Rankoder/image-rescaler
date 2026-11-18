<?php

declare(strict_types=1);

namespace Tests\Functional\Application\Services;

use App\Application\Services\ImageProcessingService;
use App\Application\Validation\ResizeRequest;
use App\Domain\Enums\ImageFormat;
use App\Domain\Enums\StorageType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestFiles;

final class ImageProcessingServiceResizeImageTest extends TestCase
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

    private function resize(string $source, ImageFormat $format, int $width, int $height): string
    {
        $filename = $this->outputFilename . '.' . $format->extension();
        $request = new ResizeRequest($source, 'source', $format, $width, $height, StorageType::Local, null);

        $result = (new ImageProcessingService())->resizeImage($request, $filename);

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

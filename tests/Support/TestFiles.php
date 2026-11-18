<?php

declare(strict_types=1);

namespace Tests\Support;

use GdImage;
use RuntimeException;

/**
 * Creates test files in a temporary directory of its own and removes them with it.
 */
final class TestFiles
{
    private readonly string $directory;

    public function __construct()
    {
        $this->directory = sys_get_temp_dir() . '/' . uniqid('image_rescaler_test_', true);
        if (!mkdir($this->directory) && !is_dir($this->directory)) {
            throw new RuntimeException('Cannot create ' . $this->directory);
        }
    }

    public function directory(): string
    {
        return $this->directory;
    }

    public function jpeg(int $width, int $height, string $name = 'image.jpg'): string
    {
        $path = $this->path($name);
        imagejpeg($this->canvas($width, $height), $path);

        return $path;
    }

    public function png(int $width, int $height, string $name = 'image.png'): string
    {
        $path = $this->path($name);
        imagepng($this->canvas($width, $height), $path);

        return $path;
    }

    public function webp(int $width, int $height, string $name = 'image.webp'): string
    {
        $path = $this->path($name);
        imagewebp($this->canvas($width, $height), $path);

        return $path;
    }

    public function gif(int $width, int $height, string $name = 'image.gif'): string
    {
        $path = $this->path($name);
        imagegif($this->canvas($width, $height), $path);

        return $path;
    }

    public function withContent(string $content, string $name): string
    {
        $path = $this->path($name);
        file_put_contents($path, $content);

        return $path;
    }

    /**
     * A valid JPEG padded with trailing bytes, so it still decodes but weighs more than the limit.
     */
    public function oversizedJpeg(int $bytes, string $name = 'big.jpg'): string
    {
        $path = $this->jpeg(100, 100, $name);
        file_put_contents($path, str_repeat("\0", $bytes), FILE_APPEND);

        return $path;
    }

    public function remove(): void
    {
        foreach (glob($this->directory . '/{,.}*', GLOB_BRACE) ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    private function path(string $name): string
    {
        return $this->directory . '/' . $name;
    }

    /**
     * Left half red, right half blue, so a crop from the centre can be told apart from a squeeze.
     */
    private function canvas(int $width, int $height): GdImage
    {
        $image = imagecreatetruecolor(max(1, $width), max(1, $height));
        if ($image === false) {
            throw new RuntimeException('Cannot create an image');
        }
        $red = (int) imagecolorallocate($image, 255, 0, 0);
        $blue = (int) imagecolorallocate($image, 0, 0, 255);
        imagefilledrectangle($image, 0, 0, intdiv($width, 2) - 1, $height - 1, $red);
        imagefilledrectangle($image, intdiv($width, 2), 0, $width - 1, $height - 1, $blue);

        return $image;
    }
}

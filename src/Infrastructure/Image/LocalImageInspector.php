<?php

declare(strict_types=1);

namespace App\Infrastructure\Image;

use App\Domain\Interfaces\ImageInspector;
use App\Domain\ValueObjects\ImageInfo;
use finfo;
use RuntimeException;

final readonly class LocalImageInspector implements ImageInspector
{
    public function sizeInBytes(string $path): int
    {
        $size = @filesize($path);
        if ($size === false) {
            throw new RuntimeException('Cannot read the size of the source file.');
        }

        return $size;
    }

    public function inspect(string $path): ?ImageInfo
    {
        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        if ($mimeType === false || !str_starts_with($mimeType, 'image/')) {
            return null;
        }

        $size = @getimagesize($path);
        if ($size === false) {
            return null;
        }

        return new ImageInfo($mimeType, $size[0], $size[1]);
    }
}

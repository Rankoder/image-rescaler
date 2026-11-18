<?php

declare(strict_types=1);

namespace App\Domain\Interfaces;

use App\Domain\ValueObjects\ImageInfo;

interface ImageInspector
{
    public function sizeInBytes(string $path): int;

    /**
     * Type and dimensions read from the content; null when the file is not an image.
     */
    public function inspect(string $path): ?ImageInfo;
}

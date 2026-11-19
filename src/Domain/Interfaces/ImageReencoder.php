<?php

declare(strict_types=1);

namespace App\Domain\Interfaces;

use App\Domain\Enums\ImageFormat;

/**
 * Re-encodes an image into a clean temporary file of the given format, dropping embedded metadata.
 */
interface ImageReencoder
{
    public function reencode(string $sourcePath, ImageFormat $format): string;
}

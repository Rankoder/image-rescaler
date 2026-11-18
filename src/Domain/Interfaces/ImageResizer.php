<?php

declare(strict_types=1);

namespace App\Domain\Interfaces;

use App\Domain\ValueObjects\ImageValueObject;

interface ImageResizer
{
    /**
     * Scales the image down until it covers width × height and cuts the overflow evenly from both sides.
     * The result is written in the format its filename's extension names.
     */
    public function cover(string $sourcePath, int $width, int $height, string $filename): ImageValueObject;
}

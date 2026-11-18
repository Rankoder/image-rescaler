<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

final readonly class ImageInfo
{
    public function __construct(
        public string $mimeType,
        public int $width,
        public int $height,
    ) {}
}

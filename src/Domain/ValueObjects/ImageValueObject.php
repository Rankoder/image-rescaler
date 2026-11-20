<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

final readonly class ImageValueObject
{
    public function __construct(
        public string $path,
        public string $filename,
    ) {}
}

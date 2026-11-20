<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Interfaces\ImageInspector;
use App\Domain\ValueObjects\ImageInfo;

final readonly class FakeImageInspector implements ImageInspector
{
    public function __construct(
        private int $bytes = 1000,
        private ?ImageInfo $image = null,
    ) {}

    public function sizeInBytes(string $path): int
    {
        return $this->bytes;
    }

    public function inspect(string $path): ?ImageInfo
    {
        return $this->image;
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Validation;

use App\Domain\Enums\ImageFormat;
use App\Domain\Enums\StorageType;

final readonly class ResizeRequest
{
    public function __construct(
        public string $sourcePath,
        public string $sourceName,
        public ImageFormat $format,
        public int $width,
        public int $height,
        public StorageType $storage,
        public ?string $outputFilename,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Application\Validation;

/**
 * Raw input of a resize, as HTTP or the CLI received it; null means the field was not given.
 */
final readonly class ResizeInput
{
    public function __construct(
        public ?SourceFile $file,
        public ?string $maxWidth,
        public ?string $maxHeight,
        public ?string $service = null,
        public ?string $outputFilename = null,
    ) {}
}

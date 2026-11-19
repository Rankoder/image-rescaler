<?php

declare(strict_types=1);

namespace App\Application\Validation;

/**
 * The image to process as an entry point found it: a readable path, or the reason it has none.
 * The field is the one errors about it are reported for: an uploaded file or an image named from the input directory.
 */
final readonly class SourceFile
{
    private function __construct(
        public string $path,
        public string $name,
        public ?ErrorCode $problem,
        public Field $field,
    ) {}

    public static function at(string $path, string $name, Field $field = Field::File): self
    {
        return new self($path, $name, null, $field);
    }

    public static function rejected(ErrorCode $problem, string $name = '', Field $field = Field::File): self
    {
        return new self('', $name, $problem, $field);
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Validation;

/**
 * The image to resize as an entry point found it: a readable path, or the reason it has none.
 */
final readonly class SourceFile
{
    private function __construct(
        public string $path,
        public string $name,
        public ?ErrorCode $problem,
    ) {}

    public static function at(string $path, string $name): self
    {
        return new self($path, $name, null);
    }

    public static function rejected(ErrorCode $problem, string $name = ''): self
    {
        return new self('', $name, $problem);
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\Cli;

use App\Application\Validation\ErrorCode;
use App\Application\Validation\SourceFile;

/**
 * The only place the CLI reads images from; a name that resolves outside it is rejected.
 */
final readonly class InputDirectory
{
    public function __construct(private string $path) {}

    public function sourceFile(string $name): SourceFile
    {
        $root = realpath($this->path);
        if ($root === false) {
            return SourceFile::rejected(ErrorCode::NotFound, $name);
        }
        if (str_starts_with($name, '/') || in_array('..', explode('/', $name), true)) {
            return SourceFile::rejected(ErrorCode::OutsideInputDirectory, $name);
        }

        $path = realpath($root . '/' . $name);
        if ($path === false || !is_file($path)) {
            return SourceFile::rejected(ErrorCode::NotFound, $name);
        }
        // A symlink inside the directory may still point outside it
        if (!str_starts_with($path, $root . '/')) {
            return SourceFile::rejected(ErrorCode::OutsideInputDirectory, $name);
        }

        return SourceFile::at($path, basename($name));
    }
}

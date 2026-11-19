<?php

declare(strict_types=1);

namespace App\Infrastructure\Filesystem;

use App\Application\Validation\ErrorCode;
use App\Application\Validation\Field;
use App\Application\Validation\SourceFile;
use App\Domain\Interfaces\ImageLibrary;

/**
 * The input directory: where the CLI and GET/POST /images read and add images. A name that resolves outside it is rejected.
 */
final readonly class InputDirectory implements ImageLibrary
{
    private const string IMAGE_NAME = '/^[A-Za-z0-9_-]{1,100}\.(jpg|jpeg|png|webp)\z/';

    public function __construct(
        private string $path,
        private ExclusiveCopy $copy = new ExclusiveCopy(),
    ) {}

    public function names(): array
    {
        $names = array_values(array_filter(
            scandir($this->path) ?: [],
            fn(string $name): bool => self::isImageName($name) && is_file($this->path . '/' . $name),
        ));
        sort($names);

        return $names;
    }

    public function addIfAbsent(string $sourcePath, string $name): bool
    {
        return $this->copy->create($sourcePath, $this->path, $name);
    }

    public static function isImageName(string $name): bool
    {
        return preg_match(self::IMAGE_NAME, $name) === 1;
    }

    public function sourceFile(string $name, Field $field = Field::File): SourceFile
    {
        $root = realpath($this->path);
        if ($root === false) {
            return SourceFile::rejected(ErrorCode::NotFound, $name, $field);
        }
        if (str_starts_with($name, '/') || in_array('..', explode('/', $name), true)) {
            return SourceFile::rejected(ErrorCode::OutsideInputDirectory, $name, $field);
        }

        $path = realpath($root . '/' . $name);
        if ($path === false || !is_file($path)) {
            return SourceFile::rejected(ErrorCode::NotFound, $name, $field);
        }
        // A symlink inside the directory may still point outside it
        if (!str_starts_with($path, $root . '/')) {
            return SourceFile::rejected(ErrorCode::OutsideInputDirectory, $name, $field);
        }

        return SourceFile::at($path, basename($name), $field);
    }
}

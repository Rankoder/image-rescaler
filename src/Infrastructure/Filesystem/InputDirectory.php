<?php

declare(strict_types=1);

namespace App\Infrastructure\Filesystem;

use App\Application\Validation\ErrorCode;
use App\Application\Validation\Field;
use App\Application\Validation\SourceFile;
use App\Domain\Enums\ImageAddOutcome;
use App\Domain\Interfaces\ImageLibrary;
use App\Domain\ValueObjects\ImageName;
use RuntimeException;

/**
 * The input directory: where the CLI and GET/POST /images read and add images. A name that resolves outside it is rejected.
 */
final readonly class InputDirectory implements ImageLibrary
{
    public function __construct(
        private string $path,
        private ExclusiveCopy $copy,
    ) {}

    public function names(): array
    {
        if (!is_dir($this->path)) {
            return [];
        }

        $names = array_values(array_filter(
            scandir($this->path) ?: [],
            fn(string $name): bool => ImageName::isValid($name) && is_file($this->path . '/' . $name),
        ));
        sort($names);

        return $names;
    }

    public function path(string $name): ?string
    {
        $source = $this->sourceFile($name);

        return $source->problem === null ? $source->path : null;
    }

    public function addIfAbsent(string $sourcePath, string $name): bool
    {
        return $this->addWithOptionalLimit($sourcePath, $name, null) === ImageAddOutcome::Added;
    }

    public function addWithinLimit(string $sourcePath, string $name, int $limit): ImageAddOutcome
    {
        try {
            return $this->addWithOptionalLimit($sourcePath, $name, $limit);
        } finally {
            if (is_file($sourcePath)) {
                unlink($sourcePath);
            }
        }
    }

    private function addWithOptionalLimit(string $sourcePath, string $name, ?int $limit): ImageAddOutcome
    {
        if (!is_dir($this->path) && !mkdir($this->path, 0777, true) && !is_dir($this->path)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $this->path));
        }

        $lock = fopen($this->path . '/.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException(sprintf('Cannot open lock file in "%s"', $this->path));
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException(sprintf('Cannot lock directory "%s"', $this->path));
            }

            if ($limit !== null && count($this->names()) >= $limit) {
                return ImageAddOutcome::LimitReached;
            }

            return $this->copy->create($sourcePath, $this->path, $name)
                ? ImageAddOutcome::Added
                : ImageAddOutcome::AlreadyExists;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
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
        if (!ImageName::isValid($name)) {
            return SourceFile::rejected(ErrorCode::NotFound, $name, $field);
        }

        $path = realpath($root . '/' . $name);
        if ($path === false || !is_file($path)) {
            return SourceFile::rejected(ErrorCode::NotFound, $name, $field);
        }
        // A symlink inside the directory may still point outside it
        if (!str_starts_with($path, $root . '/')) {
            return SourceFile::rejected(ErrorCode::OutsideInputDirectory, $name, $field);
        }

        return SourceFile::at($path, $name, $field);
    }
}

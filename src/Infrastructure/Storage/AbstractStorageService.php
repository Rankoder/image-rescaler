<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Domain\Interfaces\StorageServiceInterface;
use App\Domain\ValueObjects\ImageValueObject;

/**
 * Stores under the first free name with a _v<n> suffix, so nothing is overwritten.
 */
abstract class AbstractStorageService implements StorageServiceInterface
{
    public function upload(ImageValueObject $image): string
    {
        $filename = $this->generateVersionedFilename($image->getFilename());
        $this->store($image->getPath(), $filename);
        $this->removeTemporaryFile($image->getPath());

        return $filename;
    }

    abstract protected function fileExists(string $filename): bool;

    abstract protected function store(string $sourcePath, string $destinationFilename): void;

    private function generateVersionedFilename(string $filename): string
    {
        $baseName = pathinfo($filename, PATHINFO_FILENAME);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $extension = $extension === '' ? '' : '.' . $extension;
        $version = 1;

        while ($this->fileExists($this->createVersionedFilename($baseName, $version, $extension))) {
            $version++;
        }

        return $this->createVersionedFilename($baseName, $version, $extension);
    }

    private function createVersionedFilename(string $baseName, int $version, string $extension): string
    {
        return "{$baseName}_v{$version}{$extension}";
    }

    private function removeTemporaryFile(string $filePath): void
    {
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}

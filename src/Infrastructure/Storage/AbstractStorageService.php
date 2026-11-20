<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Domain\Interfaces\StorageServiceInterface;
use App\Domain\ValueObjects\ImageValueObject;
use RuntimeException;

/**
 * Stores under the first free name with a _v<n> suffix. Each adapter creates the file only if the
 * name is still free, in one step, so two uploads at the same moment never get the same name.
 */
abstract class AbstractStorageService implements StorageServiceInterface
{
    private const int MAX_VERSIONS = 1000;

    public function upload(ImageValueObject $image): string
    {
        try {
            $baseName = pathinfo($image->filename, PATHINFO_FILENAME);
            $extension = pathinfo($image->filename, PATHINFO_EXTENSION);
            $extension = $extension === '' ? '' : '.' . $extension;

            for ($version = 1; $version <= self::MAX_VERSIONS; $version++) {
                $filename = "{$baseName}_v{$version}{$extension}";
                if ($this->createIfAbsent($image->path, $filename)) {
                    return $filename;
                }
            }

            throw new RuntimeException(sprintf('No free name for %s after %d versions.', $image->filename, self::MAX_VERSIONS));
        } finally {
            $this->removeTemporaryFile($image->path);
        }
    }

    /**
     * Stores the file under the name unless something is stored there already; false when the name is taken.
     */
    abstract protected function createIfAbsent(string $sourcePath, string $destinationFilename): bool;

    private function removeTemporaryFile(string $filePath): void
    {
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}

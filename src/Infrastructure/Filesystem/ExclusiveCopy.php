<?php

declare(strict_types=1);

namespace App\Infrastructure\Filesystem;

use RuntimeException;

/**
 * Copies a file to a name only if the name is free, and never shows a partly copied file under it.
 */
final readonly class ExclusiveCopy
{
    public function create(string $sourcePath, string $directory, string $filename): bool
    {
        if (!is_file($sourcePath)) {
            throw new RuntimeException("File not found at path: {$sourcePath}");
        }

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $directory));
        }

        $destinationPath = $directory . '/' . $filename;
        // Written in full under a hidden name first, so a reader never sees a partly copied image
        $partialPath = sprintf('%s/.%s-%s', $directory, bin2hex(random_bytes(8)), $filename);

        try {
            if (!copy($sourcePath, $partialPath)) {
                throw new RuntimeException("Failed to copy file from {$sourcePath} to {$partialPath}");
            }

            // link() creates the name in one step and fails if it exists, so two writers never share it
            if (@link($partialPath, $destinationPath)) {
                return true;
            }
            if (file_exists($destinationPath)) {
                return false;
            }

            throw new RuntimeException("Cannot create {$destinationPath}");
        } finally {
            if (file_exists($partialPath)) {
                unlink($partialPath);
            }
        }
    }
}

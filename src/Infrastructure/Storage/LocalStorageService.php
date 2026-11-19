<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use RuntimeException;

final class LocalStorageService extends AbstractStorageService
{
    public function __construct(
        private readonly string $directory,
        private readonly string $publicBaseUrl,
    ) {}

    public function getPublicUrl(string $filePath): string
    {
        return $this->publicBaseUrl . '/files/' . $filePath;
    }

    protected function createIfAbsent(string $sourcePath, string $destinationFilename): bool
    {
        $source = @fopen($sourcePath, 'r');
        if ($source === false) {
            throw new RuntimeException("File not found at path: {$sourcePath}");
        }

        try {
            $this->ensureDirectoryExists();
            $destinationPath = $this->directory . '/' . $destinationFilename;

            // 'x' creates the file only if it does not exist yet, in one step
            $destination = @fopen($destinationPath, 'x');
            if ($destination === false) {
                if (file_exists($destinationPath)) {
                    return false;
                }

                throw new RuntimeException("Cannot create {$destinationPath}");
            }

            $copied = stream_copy_to_stream($source, $destination);
            fclose($destination);
            if ($copied === false) {
                unlink($destinationPath);

                throw new RuntimeException("Failed to copy file from {$sourcePath} to {$destinationPath}");
            }

            return true;
        } finally {
            fclose($source);
        }
    }

    private function ensureDirectoryExists(): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0777, true) && !is_dir($this->directory)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $this->directory));
        }
    }
}

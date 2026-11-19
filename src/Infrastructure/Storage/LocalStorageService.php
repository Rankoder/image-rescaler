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
        if (!is_file($sourcePath)) {
            throw new RuntimeException("File not found at path: {$sourcePath}");
        }

        $this->ensureDirectoryExists();
        $destinationPath = $this->directory . '/' . $destinationFilename;
        // Written in full under a hidden name first, so GET /files never sees a partly copied image
        $partialPath = sprintf('%s/.%s-%s', $this->directory, bin2hex(random_bytes(8)), $destinationFilename);

        try {
            if (!copy($sourcePath, $partialPath)) {
                throw new RuntimeException("Failed to copy file from {$sourcePath} to {$partialPath}");
            }

            // link() creates the name in one step and fails if it exists, so two uploads never share it
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

    private function ensureDirectoryExists(): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0777, true) && !is_dir($this->directory)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $this->directory));
        }
    }
}

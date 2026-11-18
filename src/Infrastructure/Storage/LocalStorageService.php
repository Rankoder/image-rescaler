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
        return $this->publicBaseUrl . '/' . $filePath;
    }

    protected function store(string $sourcePath, string $destinationFilename): void
    {
        if (!file_exists($sourcePath)) {
            throw new RuntimeException("File not found at path: {$sourcePath}");
        }

        $this->ensureDirectoryExists();
        $destinationPath = $this->directory . '/' . $destinationFilename;
        if (!copy($sourcePath, $destinationPath)) {
            throw new RuntimeException("Failed to copy file from {$sourcePath} to {$destinationPath}");
        }
    }

    protected function fileExists(string $filename): bool
    {
        return file_exists($this->directory . '/' . $filename);
    }

    private function ensureDirectoryExists(): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0777, true) && !is_dir($this->directory)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $this->directory));
        }
    }
}

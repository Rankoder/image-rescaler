<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Interfaces\StorageServiceInterface;
use RuntimeException;

class LocalStorageService extends AbstractStorageService implements StorageServiceInterface
{
    private const DEFAULT_HOST = 'localhost';
    private const DEFAULT_PORT = '8080';

    public function __construct()
    {
        parent::__construct();
    }

    protected function store(string $sourcePath, string $destinationFilename): void
    {
        $destinationPath = $this->storagePath . $destinationFilename;

        $this->ensureStoragePathExists();

        if (file_exists($sourcePath)) {
            $this->copyFile($sourcePath, $destinationPath);
            $this->removeFile($sourcePath);
        } else {
            throw new RuntimeException("File not found at path: {$sourcePath}");
        }
    }

    private function ensureStoragePathExists(): void
    {
        if (!file_exists($this->storagePath)) {
            if (!mkdir($this->storagePath, 0777, true) && !is_dir($this->storagePath)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $this->storagePath));
            }
        }
    }

    private function copyFile(string $sourcePath, string $destinationPath): void
    {
        if (!copy($sourcePath, $destinationPath)) {
            throw new RuntimeException("Failed to copy file from {$sourcePath} to {$destinationPath}");
        }
    }

    private function removeFile(string $filePath): void
    {
        if (!unlink($filePath)) {
            throw new RuntimeException("Failed to remove file at {$filePath}");
        }
    }

    protected function fileExists(string $filename): bool
    {
        return file_exists($this->storagePath . $filename);
    }

    public function getPublicUrl(string $filePath): string
    {
        $host = getenv('APP_HOST') ?: self::DEFAULT_HOST;
        $port = getenv('APP_PORT') ?: self::DEFAULT_PORT;
        return sprintf('http://%s:%s/%s', $host, $port, $filePath);
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\ValueObjects\ImageValueObject;
use Exception;

abstract class AbstractStorageService
{
    protected string $storagePath;

    public function __construct()
    {
        $this->storagePath = $this->initializeStoragePath();
    }

    private function initializeStoragePath(): string
    {
        return __DIR__ . '/../../../storage/';
    }

    public function upload(ImageValueObject $image): string
    {
        $this->validateImage($image);
        $filename = $this->generateVersionedFilename($image->getFilename());
        $this->store($image->getPath(), $filename);
        $this->removeTemporaryFile($image->getPath());
        return $filename;
    }

    private function generateVersionedFilename(string $filename): string
    {
        $pathInfo = pathinfo($filename);
        $baseName = $pathInfo['filename'];
        $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
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

    private function validateImage(ImageValueObject $image): void
    {
        $this->checkEmptyPath($image);
        $this->validateFilePath($image->getPath());
        $this->validateFileExtension($image);
        $this->validateFileSize($image);
    }

    private function checkEmptyPath(ImageValueObject $image): void
    {
        if (empty($image->getPath())) {
            throw new Exception('Image path is empty.');
        }
    }

    private function validateFileExtension(ImageValueObject $image): void
    {
        $allowedExtensions = $this->getAllowedExtensions();
        $extension = $this->getFileExtension($image->getFilename());

        if (!in_array($extension, $allowedExtensions)) {
            throw new Exception("Invalid image file type: {$extension}. Allowed types are: " . implode(', ', $allowedExtensions));
        }
    }

    private function getAllowedExtensions(): array
    {
        return array_map(
            fn($type) => str_replace('image/', '', $type),
            explode(',', getenv('ALLOWED_IMAGE_TYPES')),
        );
    }

    private function getFileExtension(string $filename): string
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    private function validateFileSize(ImageValueObject $image): void
    {
        $maxFileSize = $this->getMaxFileSize();
        if (filesize($image->getPath()) > $maxFileSize) {
            throw new Exception("File size exceeds the maximum limit of " . ($maxFileSize / 1024 / 1024) . "MB.");
        }
    }

    private function getMaxFileSize(): int
    {
        return (int) getenv('MAX_FILE_SIZE');
    }

    private function validateFilePath(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new Exception("The file at path: {$filePath} does not exist.");
        }
        if (!is_readable($filePath)) {
            throw new Exception("The file at path: {$filePath} is not readable.");
        }
    }

    private function removeTemporaryFile(string $filePath): void
    {
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    abstract protected function fileExists(string $filename): bool;

    abstract protected function store(string $sourcePath, string $destinationFilename): void;
}

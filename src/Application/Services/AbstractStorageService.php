<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\ValueObjects\ImageValueObject;

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

    private function removeTemporaryFile(string $filePath): void
    {
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    abstract protected function fileExists(string $filename): bool;

    abstract protected function store(string $sourcePath, string $destinationFilename): void;
}

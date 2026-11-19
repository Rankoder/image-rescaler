<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Infrastructure\Filesystem\ExclusiveCopy;

final class LocalStorageService extends AbstractStorageService
{
    public function __construct(
        private readonly string $directory,
        private readonly string $publicBaseUrl,
        private readonly ExclusiveCopy $copy = new ExclusiveCopy(),
    ) {}

    public function getPublicUrl(string $filePath): string
    {
        return $this->publicBaseUrl . '/files/' . $filePath;
    }

    protected function createIfAbsent(string $sourcePath, string $destinationFilename): bool
    {
        return $this->copy->create($sourcePath, $this->directory, $destinationFilename);
    }
}

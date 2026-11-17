<?php

declare(strict_types=1);

namespace App\Application\Services;

use Exception;

class ImageValidationService
{
    private const ERROR_INVALID_FILE_TYPE = "Invalid image file type: %s. Allowed types are: %s";
    private const ERROR_FILE_SIZE_EXCEEDED = "File size exceeds the maximum limit of %s MB.";
    private const ERROR_IMAGE_DIMENSIONS_EXCEEDED = "Image dimensions exceed the maximum allowed size of %sx%s.";

    public function validate(array $file, int $maxWidth, int $maxHeight): void
    {
        $this->validateFileType($file);
        $this->validateFileSize($file);
        $this->validateImageDimensions($maxWidth, $maxHeight);
    }

    private function validateFileType(array $file): void
    {
        $allowedExtensions = $this->getAllowedExtensions();
        $extension = $this->getFileExtension($file['name']);

        if (!in_array($extension, $allowedExtensions, true)) {
            throw new Exception(sprintf(self::ERROR_INVALID_FILE_TYPE, $extension, implode(', ', $allowedExtensions)));
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

    private function validateFileSize(array $file): void
    {
        $maxFileSize = (int) getenv('MAX_FILE_SIZE');
        if (filesize($file['tmp_name']) > $maxFileSize) {
            throw new Exception(sprintf(self::ERROR_FILE_SIZE_EXCEEDED, $maxFileSize / 1024 / 1024));
        }
    }

    private function validateImageDimensions(int $maxWidth, int $maxHeight): void
    {
        $maxAllowedWidth = (int) getenv('MAX_IMAGE_WIDTH');
        $maxAllowedHeight = (int) getenv('MAX_IMAGE_HEIGHT');

        if ($maxWidth > $maxAllowedWidth || $maxHeight > $maxAllowedHeight) {
            throw new Exception(sprintf(self::ERROR_IMAGE_DIMENSIONS_EXCEEDED, $maxAllowedWidth, $maxAllowedHeight));
        }
    }
}

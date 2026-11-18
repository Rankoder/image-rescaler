<?php

declare(strict_types=1);

namespace App\Domain\Interfaces;

use App\Domain\ValueObjects\ImageValueObject;

interface StorageServiceInterface
{
    /**
     * Upload a file to the storage service.
     *
     * @param ImageValueObject $image The image value object to be uploaded.
     * @return string The URL or path of the uploaded file.
     */
    public function upload(ImageValueObject $image): string;

    public function getPublicUrl(string $filePath): string;
}

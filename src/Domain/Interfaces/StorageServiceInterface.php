<?php

declare(strict_types=1);

namespace App\Domain\Interfaces;

use App\Domain\ValueObjects\ImageValueObject;

interface StorageServiceInterface
{
    public function upload(ImageValueObject $image): string;

    public function getPublicUrl(string $filePath): string;
}

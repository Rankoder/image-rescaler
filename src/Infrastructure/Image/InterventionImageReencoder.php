<?php

declare(strict_types=1);

namespace App\Infrastructure\Image;

use App\Domain\Enums\ImageFormat;
use App\Domain\Interfaces\ImageReencoder;
use Intervention\Image\ImageManager;

final readonly class InterventionImageReencoder implements ImageReencoder
{
    public function __construct(
        private ImageManager $imageManager,
        private string $temporaryDirectory,
    ) {}

    public function reencode(string $sourcePath, ImageFormat $format): string
    {
        $image = $this->imageManager->decodePath($sourcePath);
        $path = sprintf('%s/%s.%s', $this->temporaryDirectory, bin2hex(random_bytes(8)), $format->extension());
        $image->save($path);

        return $path;
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\Image;

use App\Domain\Interfaces\ImageResizer;
use App\Domain\ValueObjects\ImageValueObject;
use Intervention\Image\ImageManager;

final readonly class InterventionImageResizer implements ImageResizer
{
    public function __construct(
        private ImageManager $imageManager,
        private string $temporaryDirectory,
    ) {}

    public function cover(string $sourcePath, int $width, int $height, string $filename): ImageValueObject
    {
        $image = $this->imageManager->decodePath($sourcePath);
        $image->cover($width, $height);

        $path = $this->temporaryDirectory . '/' . $filename;
        $image->save($path);

        return new ImageValueObject($path, $filename);
    }
}

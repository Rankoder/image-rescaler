<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Validation\ResizeRequest;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use App\Domain\ValueObjects\ImageValueObject;

class ImageProcessingService
{
    private ImageManager $imageManager;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new GdDriver());
    }

    public function outputFilename(ResizeRequest $request): string
    {
        $baseName = $request->outputFilename ?? $this->generatedBaseName($request->sourceName);

        return $baseName . '.' . $request->format->extension();
    }

    public function resizeImage(ResizeRequest $request, string $outputFilename): ImageValueObject
    {
        $image = $this->imageManager->decodePath($request->sourcePath);
        // Validation guarantees the source is at least as large as the frame, so cover never enlarges it
        $image->cover($request->width, $request->height);

        $tempFilePath = $this->getTemporaryFilePath($outputFilename);
        $image->save($tempFilePath);

        return new ImageValueObject($tempFilePath, $outputFilename);
    }

    // The client's file name only shapes the default name, so whatever it holds is reduced to safe characters
    private function generatedBaseName(string $sourceName): string
    {
        $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', pathinfo($sourceName, PATHINFO_FILENAME)) ?? '';

        return 'resized_' . substr($safeName, 0, 100) . '_' . uniqid();
    }

    private function getTemporaryFilePath(string $outputFilename): string
    {
        return sys_get_temp_dir() . '/' . $outputFilename;
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Services;

use Intervention\Image\ImageManager;
use App\Domain\ValueObjects\ImageValueObject;

class ImageProcessingService
{
    private ImageManager $imageManager;

    public function __construct()
    {
        $this->imageManager = ImageManager::gd();
    }

    public function generateUniqueFilename(string $filename, string $outputFilename = ''): string
    {
        $extension = $this->getFileExtension($filename);

        if ($outputFilename) {
            return $this->appendExtensionIfMissing($outputFilename, $extension);
        }

        return $this->generateResizedFilename($filename, $extension);
    }

    public function resizeImage(array $file, int $maxWidth, int $maxHeight, string $outputFilename): ImageValueObject
    {
        $image = $this->imageManager->read($file['tmp_name']);
        $image->scaleDown($maxWidth, $maxHeight);

        $tempFilePath = $this->getTemporaryFilePath($outputFilename);
        $image->save($tempFilePath);

        return new ImageValueObject($tempFilePath, $outputFilename);
    }

    private function getFileExtension(string $filename): string
    {
        $pathInfo = pathinfo($filename);
        return isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
    }

    private function appendExtensionIfMissing(string $filename, string $extension): string
    {
        $outputPathInfo = pathinfo($filename);
        if (!isset($outputPathInfo['extension']) && $extension) {
            $filename .= $extension;
        }
        return $filename;
    }

    private function generateResizedFilename(string $filename, string $extension): string
    {
        $pathInfo = pathinfo($filename);
        return 'resized_' . $pathInfo['filename'] . '_' . uniqid() . $extension;
    }

    private function getTemporaryFilePath(string $outputFilename): string
    {
        return sys_get_temp_dir() . '/' . $outputFilename;
    }
}

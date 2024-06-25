<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

class ImageValueObject
{
    private string $path;
    private string $filename;

    /**
     * ImageValueObject constructor.
     *
     * @param string $path The path to the image.
     * @param string $filename The name of the image file.
     */
    public function __construct(string $path, string $filename)
    {
        $this->path = $path;
        $this->filename = $filename;
    }

    /**
     * Get the path to the image.
     *
     * @return string The image path.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get the name of the image file.
     *
     * @return string The image filename.
     */
    public function getFilename(): string
    {
        return $this->filename;
    }
}

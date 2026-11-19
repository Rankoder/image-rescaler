<?php

declare(strict_types=1);

namespace App\Domain\Interfaces;

/**
 * The input directory: images the app ships with and the ones added to it.
 */
interface ImageLibrary
{
    /**
     * @return list<string> names of the images, sorted
     */
    public function names(): array;

    /**
     * Adds the file under the name unless an image of that name exists; false when the name is taken.
     */
    public function addIfAbsent(string $sourcePath, string $name): bool;
}

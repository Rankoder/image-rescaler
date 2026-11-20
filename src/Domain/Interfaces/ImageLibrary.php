<?php

declare(strict_types=1);

namespace App\Domain\Interfaces;

use App\Domain\Enums\ImageAddOutcome;

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
     * The readable path of the image of that name, null when the directory has none.
     */
    public function path(string $name): ?string;

    /**
     * Adds the temporary file under the name unless an image of that name exists or the limit is reached, and removes the temporary file.
     */
    public function addWithinLimit(string $sourcePath, string $name, int $limit): ImageAddOutcome;
}

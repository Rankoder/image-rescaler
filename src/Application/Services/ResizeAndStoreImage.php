<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Validation\ResizeInput;
use App\Application\Validation\ResizeRequestValidator;
use App\Application\Validation\ValidationFailed;
use App\Domain\Interfaces\ImageResizer;
use App\Domain\Interfaces\StorageRegistry;

/**
 * The resize use case shared by HTTP and the CLI: validate, crop, store.
 */
final readonly class ResizeAndStoreImage
{
    public function __construct(
        private ResizeRequestValidator $validator,
        private OutputFilename $outputFilename,
        private ImageResizer $resizer,
        private StorageRegistry $storages,
    ) {}

    /**
     * @throws ValidationFailed
     */
    public function handle(ResizeInput $input): StoredImage
    {
        $request = $this->validator->validate($input);
        // Chosen before resizing, so a storage that cannot be built leaves no temporary file behind
        $storage = $this->storages->get($request->storage);

        $image = $this->resizer->cover(
            $request->sourcePath,
            $request->width,
            $request->height,
            $this->outputFilename->for($request),
        );
        $path = $storage->upload($image);

        return new StoredImage($path, $storage->getPublicUrl($path));
    }
}

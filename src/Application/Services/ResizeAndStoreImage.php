<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Validation\ResizeInput;
use App\Application\Validation\ResizeRequestValidator;
use App\Application\Validation\ValidationFailed;

/**
 * The resize use case shared by HTTP and the CLI: validate, crop, store.
 */
final readonly class ResizeAndStoreImage
{
    public function __construct(
        private ResizeRequestValidator $validator,
        private ImageProcessingService $imageProcessing,
        private StorageServiceFactory $storageFactory,
    ) {}

    /**
     * @throws ValidationFailed
     */
    public function handle(ResizeInput $input): StoredImage
    {
        $request = $this->validator->validate($input);

        $image = $this->imageProcessing->resizeImage($request, $this->imageProcessing->outputFilename($request));

        $storage = $this->storageFactory->create($request->storage->value);
        $path = $storage->upload($image);

        return new StoredImage($path, $storage->getPublicUrl($path));
    }
}

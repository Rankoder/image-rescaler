<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Application\Services\ImageProcessingService;
use App\Application\Services\ResizeAndStoreImage;
use App\Application\Services\StorageServiceFactory;
use App\Application\Validation\ResizeRequestValidator;
use App\Infrastructure\Controllers\ImageController;
use App\Infrastructure\Image\LocalImageInspector;
use Nyholm\Psr7\Factory\Psr17Factory;

/**
 * The same wiring as routes.php, for tests that go through the real services.
 */
final class Wiring
{
    public static function resizeAndStoreImage(
        ?ImageProcessingService $imageProcessing = null,
        ?StorageServiceFactory $storageFactory = null,
    ): ResizeAndStoreImage {
        return new ResizeAndStoreImage(
            new ResizeRequestValidator(new LocalImageInspector()),
            $imageProcessing ?? new ImageProcessingService(),
            $storageFactory ?? new StorageServiceFactory(),
        );
    }

    public static function imageController(?ResizeAndStoreImage $resizeAndStoreImage = null): ImageController
    {
        $factory = new Psr17Factory();

        return new ImageController($resizeAndStoreImage ?? self::resizeAndStoreImage(), $factory, $factory);
    }
}

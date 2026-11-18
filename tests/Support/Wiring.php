<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Application\Services\OutputFilename;
use App\Application\Services\ResizeAndStoreImage;
use App\Application\Services\StorageServiceFactory;
use App\Application\Validation\ResizeRequestValidator;
use App\Infrastructure\Controllers\ImageController;
use App\Domain\Interfaces\ImageResizer;
use App\Infrastructure\Image\InterventionImageResizer;
use App\Infrastructure\Image\LocalImageInspector;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Nyholm\Psr7\Factory\Psr17Factory;

/**
 * The same wiring as routes.php, for tests that go through the real services.
 */
final class Wiring
{
    public const int POST_MAX_SIZE = 16 * 1024 * 1024;

    public static function resizeAndStoreImage(
        ?ImageResizer $resizer = null,
        ?StorageServiceFactory $storageFactory = null,
    ): ResizeAndStoreImage {
        return new ResizeAndStoreImage(
            new ResizeRequestValidator(new LocalImageInspector()),
            new OutputFilename(),
            $resizer ?? new InterventionImageResizer(new ImageManager(new GdDriver()), sys_get_temp_dir()),
            $storageFactory ?? new StorageServiceFactory(),
        );
    }

    public static function imageController(?ResizeAndStoreImage $resizeAndStoreImage = null): ImageController
    {
        $factory = new Psr17Factory();

        return new ImageController(
            $resizeAndStoreImage ?? self::resizeAndStoreImage(),
            $factory,
            $factory,
            self::POST_MAX_SIZE,
        );
    }
}

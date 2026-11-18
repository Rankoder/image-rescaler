<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Application\Services\OutputFilename;
use App\Application\Services\ResizeAndStoreImage;
use App\Application\Validation\ResizeRequestValidator;
use App\Domain\Interfaces\ImageResizer;
use App\Domain\Interfaces\StorageRegistry;
use App\Infrastructure\Config\AppConfig;
use App\Infrastructure\Container;
use App\Infrastructure\Controllers\ImageController;
use App\Infrastructure\Image\LocalImageInspector;

/**
 * The application's own Container, configured from phpunit.xml, with a way to swap the outside world for mocks.
 */
final class Wiring
{
    public static function config(): AppConfig
    {
        return AppConfig::fromEnvironment(getenv(), dirname(__DIR__, 2));
    }

    public static function container(): Container
    {
        return new Container(self::config());
    }

    public static function resizeAndStoreImage(
        ?ImageResizer $resizer = null,
        ?StorageRegistry $storages = null,
    ): ResizeAndStoreImage {
        return new ResizeAndStoreImage(
            new ResizeRequestValidator(new LocalImageInspector()),
            new OutputFilename(),
            $resizer ?? self::container()->imageResizer(),
            $storages ?? self::container()->storageRegistry(),
        );
    }

    public static function imageController(?ResizeAndStoreImage $resizeAndStoreImage = null): ImageController
    {
        if ($resizeAndStoreImage === null) {
            return self::container()->imageController();
        }

        $factory = self::container()->httpFactory();

        return new ImageController($resizeAndStoreImage, $factory, $factory, self::config()->postMaxSize);
    }
}

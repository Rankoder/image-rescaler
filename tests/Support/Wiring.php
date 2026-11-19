<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Application\Services\OutputFilename;
use App\Application\Services\ResizeAndStoreImage;
use App\Application\Validation\NameRule;
use App\Application\Validation\ResizeRequestValidator;
use App\Domain\Interfaces\ImageResizer;
use App\Domain\Interfaces\StorageRegistry;
use App\Infrastructure\Config\AppConfig;
use App\Infrastructure\Container;
use App\Infrastructure\Controllers\ImageController;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

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
            new ResizeRequestValidator(self::container()->imageFileRules(), new NameRule()),
            new OutputFilename(),
            $resizer ?? self::container()->imageResizer(),
            $storages ?? self::container()->storageRegistry(),
        );
    }

    public static function imageController(
        ?ResizeAndStoreImage $resizeAndStoreImage = null,
        LoggerInterface $logger = new NullLogger(),
    ): ImageController {
        $factory = self::container()->httpFactory();

        return new ImageController(
            $resizeAndStoreImage ?? self::container()->resizeAndStoreImage(),
            $factory,
            $factory,
            self::config()->postMaxSize,
            $logger,
        );
    }
}

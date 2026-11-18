<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Application\Services\OutputFilename;
use App\Application\Services\ResizeAndStoreImage;
use App\Application\Services\StorageServiceFactory;
use App\Application\Validation\ResizeRequestValidator;
use App\Domain\Interfaces\ImageResizer;
use App\Infrastructure\Cli\InputDirectory;
use App\Infrastructure\Cli\ResizeCommand;
use App\Infrastructure\Config\AppConfig;
use App\Infrastructure\Controllers\HealthController;
use App\Infrastructure\Controllers\ImageController;
use App\Infrastructure\Controllers\NotFoundController;
use App\Infrastructure\Image\InterventionImageResizer;
use App\Infrastructure\Image\LocalImageInspector;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Nyholm\Psr7\Factory\Psr17Factory;

/**
 * The one place where the application is wired together, for HTTP, the CLI and the tests.
 */
final readonly class Container
{
    private Psr17Factory $httpFactory;

    public function __construct(private AppConfig $config)
    {
        $this->httpFactory = new Psr17Factory();
    }

    public function httpFactory(): Psr17Factory
    {
        return $this->httpFactory;
    }

    public function healthController(): HealthController
    {
        return new HealthController($this->httpFactory, $this->httpFactory);
    }

    public function notFoundController(): NotFoundController
    {
        return new NotFoundController($this->httpFactory, $this->httpFactory);
    }

    public function imageController(): ImageController
    {
        return new ImageController(
            $this->resizeAndStoreImage(),
            $this->httpFactory,
            $this->httpFactory,
            $this->config->postMaxSize,
        );
    }

    public function resizeCommand(): ResizeCommand
    {
        return new ResizeCommand($this->resizeAndStoreImage(), new InputDirectory($this->config->inputDirectory));
    }

    public function resizeAndStoreImage(): ResizeAndStoreImage
    {
        return new ResizeAndStoreImage(
            new ResizeRequestValidator(new LocalImageInspector()),
            new OutputFilename(),
            $this->imageResizer(),
            new StorageServiceFactory(),
        );
    }

    public function imageResizer(): ImageResizer
    {
        return new InterventionImageResizer(new ImageManager(new GdDriver()), $this->config->temporaryDirectory);
    }
}

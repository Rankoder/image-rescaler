<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Application\Services\OutputFilename;
use App\Application\Services\ResizeAndStoreImage;
use App\Application\Validation\ResizeRequestValidator;
use App\Domain\Enums\StorageType;
use App\Domain\Interfaces\ImageResizer;
use App\Domain\Interfaces\StorageRegistry;
use App\Infrastructure\Cli\InputDirectory;
use App\Infrastructure\Cli\ResizeCommand;
use App\Infrastructure\Config\AppConfig;
use App\Infrastructure\Controllers\FileController;
use App\Infrastructure\Controllers\HealthController;
use App\Infrastructure\Controllers\ImageController;
use App\Infrastructure\Controllers\NotFoundController;
use App\Infrastructure\Image\InterventionImageResizer;
use App\Infrastructure\Image\LocalImageInspector;
use App\Infrastructure\Logging\ErrorLogLogger;
use App\Infrastructure\Storage\DropboxStorageService;
use App\Infrastructure\Storage\LazyStorageRegistry;
use App\Infrastructure\Storage\LocalStorageService;
use App\Infrastructure\Storage\S3StorageService;
use Aws\S3\S3Client;
use Kunnu\Dropbox\Dropbox;
use Kunnu\Dropbox\DropboxApp;
use RuntimeException;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Log\LoggerInterface;

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

    public function fileController(): FileController
    {
        return new FileController(
            $this->config->storageDirectory,
            $this->httpFactory,
            $this->httpFactory,
            $this->notFoundController(),
        );
    }

    public function imageController(): ImageController
    {
        return new ImageController(
            $this->resizeAndStoreImage(),
            $this->httpFactory,
            $this->httpFactory,
            $this->config->postMaxSize,
            $this->logger(),
        );
    }

    public function logger(): LoggerInterface
    {
        return new ErrorLogLogger();
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
            $this->storageRegistry(),
        );
    }

    public function imageResizer(): ImageResizer
    {
        return new InterventionImageResizer(new ImageManager(new GdDriver()), $this->config->temporaryDirectory);
    }

    public function storageRegistry(): StorageRegistry
    {
        $config = $this->config;

        return new LazyStorageRegistry([
            StorageType::Local->value => static fn(): LocalStorageService => new LocalStorageService(
                $config->storageDirectory,
                $config->publicBaseUrl,
            ),
            StorageType::S3->value => static fn(): S3StorageService => new S3StorageService(
                new S3Client([
                    'credentials' => ['key' => $config->aws->accessKeyId, 'secret' => $config->aws->secretAccessKey],
                    'region' => $config->aws->region,
                    'version' => 'latest',
                ]),
                $config->aws->bucket,
                $config->aws->region,
            ),
            StorageType::Dropbox->value => static fn(): DropboxStorageService => self::dropboxStorage($config),
        ]);
    }

    private static function dropboxStorage(AppConfig $config): DropboxStorageService
    {
        if ($config->dropboxAccessToken === '') {
            throw new RuntimeException('Dropbox access token is not set.');
        }

        return new DropboxStorageService(
            new Dropbox(new DropboxApp('', '', $config->dropboxAccessToken)),
            $config->dropboxDirectory,
        );
    }
}

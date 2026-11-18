<?php

declare(strict_types=1);

namespace Tests\Functional\Infrastructure\Controllers;

use App\Application\Services\StorageServiceFactory;
use App\Domain\Interfaces\ImageResizer;
use App\Domain\Interfaces\StorageServiceInterface;
use App\Domain\ValueObjects\ImageValueObject;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Tests\Support\Wiring;

/**
 * The controller with real validation and the processing and storage mocked, so S3 and Dropbox need no accounts.
 */
final class ImageControllerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const FIXTURE = __DIR__ . '/../../../fixtures/test_image.jpg';

    private ImageResizer&MockInterface $resizer;
    private StorageServiceFactory&MockInterface $storageFactory;
    private StorageServiceInterface&MockInterface $storage;

    protected function setUp(): void
    {
        $this->resizer = Mockery::mock(ImageResizer::class);
        $this->storageFactory = Mockery::mock(StorageServiceFactory::class);
        $this->storage = Mockery::mock(StorageServiceInterface::class);

        $this->resizer->shouldReceive('cover')->andReturn(new ImageValueObject('/tmp/thumbnail.jpg', 'thumbnail.jpg'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function services(): iterable
    {
        yield 'local' => ['local'];
        yield 's3' => ['s3'];
        yield 'dropbox' => ['dropbox'];
    }

    #[DataProvider('services')]
    public function testStoresInTheChosenServiceAndAnswersWithItsPathAndUrl(string $service): void
    {
        $this->storageFactory->shouldReceive('create')->once()->with($service)->andReturn($this->storage);
        $this->storage->shouldReceive('upload')->once()->andReturn('thumbnail_v1.jpg');
        $this->storage->shouldReceive('getPublicUrl')->with('thumbnail_v1.jpg')->andReturn('https://storage.test/thumbnail_v1.jpg');

        $response = $this->upload($service);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            '{"status":"success","path":"thumbnail_v1.jpg","url":"https:\/\/storage.test\/thumbnail_v1.jpg"}',
            (string) $response->getBody(),
        );
    }

    public function testAStorageFailureIsAServerError(): void
    {
        $this->storageFactory->shouldReceive('create')->andReturn($this->storage);
        $this->storage->shouldReceive('upload')->andThrow(new RuntimeException('Storage is down'));

        $response = $this->upload('local');

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('{"status":"error","message":"Storage is down"}', (string) $response->getBody());
    }

    public function testInvalidInputNeverReachesProcessingOrStorage(): void
    {
        $this->resizer->shouldNotReceive('cover');
        $this->storageFactory->shouldNotReceive('create');

        $response = $this->upload('ftp');

        $this->assertSame(422, $response->getStatusCode());
    }

    private function upload(string $service): ResponseInterface
    {
        $factory = new Psr17Factory();
        $file = $factory->createUploadedFile(
            $factory->createStreamFromFile(self::FIXTURE),
            (int) filesize(self::FIXTURE),
            UPLOAD_ERR_OK,
            'photo.jpg',
        );

        $request = (new ServerRequest('POST', '/upload', ['Content-Type' => 'multipart/form-data; boundary=test']))
            ->withUploadedFiles(['file' => $file])
            ->withParsedBody(['max_width' => '60', 'max_height' => '40', 'service' => $service]);

        return Wiring::imageController(Wiring::resizeAndStoreImage($this->resizer, $this->storageFactory))
            ->upload($request);
    }
}

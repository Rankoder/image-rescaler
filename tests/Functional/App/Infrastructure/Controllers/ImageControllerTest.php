<?php

declare(strict_types=1);

namespace App\Infrastructure\Controllers;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Mockery;
use App\Application\Services\ImageProcessingService;
use App\Application\Services\StorageServiceFactory;
use App\Application\Services\ImageValidationService;
use Psr\Http\Message\UploadedFileInterface;
use App\Domain\ValueObjects\ImageValueObject;
use App\Infrastructure\Controllers\ImageController;

class ImageControllerTest extends TestCase
{
    private $psr17Factory;
    private $imageProcessingServiceMock;
    private $storageServiceFactoryMock;
    private $imageValidationServiceMock;
    private $controller;
    private string $tempFilePath;

    protected function setUp(): void
    {
        ini_set('memory_limit', '256M'); // Set higher memory limit

        $this->psr17Factory = new Psr17Factory();

        $this->imageProcessingServiceMock = Mockery::mock(ImageProcessingService::class);
        $this->storageServiceFactoryMock = Mockery::mock(StorageServiceFactory::class);
        $this->imageValidationServiceMock = Mockery::mock(ImageValidationService::class);

        // Create controller instance with mocks
        $this->controller = new ImageController();
        $reflection = new \ReflectionClass($this->controller);
        $properties = [
            'psr17Factory' => $this->psr17Factory,
            'imageProcessingService' => $this->imageProcessingServiceMock,
            'storageServiceFactory' => $this->storageServiceFactoryMock,
            'imageValidationService' => $this->imageValidationServiceMock,
        ];
        foreach ($properties as $property => $mock) {
            $reflectedProperty = $reflection->getProperty($property);
            $reflectedProperty->setValue($this->controller, $mock);
        }

        // Mock environment variables
        putenv('AWS_ACCESS_KEY_ID=your_aws_access_key_id');
        putenv('AWS_SECRET_ACCESS_KEY=your_aws_secret_access_key');
        putenv('AWS_REGION=your_aws_region');
        putenv('AWS_BUCKET_NAME=your_aws_bucket_name');
        putenv('DROPBOX_ACCESS_TOKEN=your_dropbox_access_token');
        putenv('ALLOWED_IMAGE_TYPES=image/jpeg,image/png,image/jpg');
        putenv('MAX_FILE_SIZE=10485760'); // 10 MB
        putenv('MAX_IMAGE_WIDTH=6000');
        putenv('MAX_IMAGE_HEIGHT=6000');

        // Create a temporary test image file
        $this->tempFilePath = sys_get_temp_dir() . '/test_image.jpg';
        $this->createTestImage($this->tempFilePath);
    }

    #[DataProvider('uploadDataProvider')]
    public function testUpload(array $uploadedFiles, array $parsedBody, int $expectedStatusCode, string $expectedResponseMessage): void
    {
        $request = new ServerRequest('POST', '/upload', [], null, '1.1', $_SERVER);
        $request = $request->withUploadedFiles($uploadedFiles)
            ->withParsedBody($parsedBody);

        if ($expectedStatusCode === 200) {
            $fileArray = [
                'tmp_name' => $uploadedFiles['file']->getStream()->getMetadata('uri'),
                'name' => $uploadedFiles['file']->getClientFilename(),
            ];

            $this->imageValidationServiceMock->shouldReceive('validate')->once()->with($fileArray, $parsedBody['max_width'], $parsedBody['max_height']);
            $this->imageProcessingServiceMock->shouldReceive('generateUniqueFilename')->once()->andReturn('unique_filename.jpg');
            $this->imageProcessingServiceMock->shouldReceive('resizeImage')->once()->andReturn(new ImageValueObject('/tmp/unique_filename.jpg', 'unique_filename.jpg'));

            $storageServiceMock = Mockery::mock(\App\Domain\Interfaces\StorageServiceInterface::class);
            $this->storageServiceFactoryMock->shouldReceive('create')->andReturn($storageServiceMock);
            $storageServiceMock->shouldReceive('upload')->andReturn('uploaded/unique_filename.jpg');
            $storageServiceMock->shouldReceive('getPublicUrl')->andReturn('http://localhost/uploaded/unique_filename.jpg');
        }

        $response = $this->controller->upload($request);

        $this->assertSame($expectedStatusCode, $response->getStatusCode());
        $this->assertStringContainsString($expectedResponseMessage, (string) $response->getBody());
    }

    public static function uploadDataProvider(): array
    {
        $stream = Mockery::mock(\Psr\Http\Message\StreamInterface::class);
        $stream->shouldReceive('getMetadata')->andReturn('/tmp/test_image.jpg');

        $uploadedFile = Mockery::mock(UploadedFileInterface::class);
        $uploadedFile->shouldReceive('getStream')->andReturn($stream);
        $uploadedFile->shouldReceive('getClientFilename')->andReturn('test_image.jpg');

        return [
            'valid request' => [
                'uploadedFiles' => ['file' => $uploadedFile],
                'parsedBody' => [
                    'service' => 'local',
                    'max_width' => 4000,
                    'max_height' => 3000,
                    'output_filename' => '',
                ],
                'expectedStatusCode' => 200,
                'expectedResponseMessage' => 'success',
            ],
            'invalid request parameters' => [
                'uploadedFiles' => [],
                'parsedBody' => [
                    'service' => 'local',
                    'max_width' => 4000,
                    'max_height' => 3000,
                    'output_filename' => '',
                ],
                'expectedStatusCode' => 400,
                'expectedResponseMessage' => 'Invalid request parameters',
            ],
            's3 upload' => [
                'uploadedFiles' => ['file' => $uploadedFile],
                'parsedBody' => [
                    'service' => 's3',
                    'max_width' => 4000,
                    'max_height' => 3000,
                    'output_filename' => '',
                ],
                'expectedStatusCode' => 200,
                'expectedResponseMessage' => 'success',
            ],
            'dropbox upload' => [
                'uploadedFiles' => ['file' => $uploadedFile],
                'parsedBody' => [
                    'service' => 'dropbox',
                    'max_width' => 4000,
                    'max_height' => 3000,
                    'output_filename' => '',
                ],
                'expectedStatusCode' => 200,
                'expectedResponseMessage' => 'success',
            ],
        ];
    }

    private function createTestImage(string $path): void
    {
        $image = imagecreatetruecolor(100, 100);
        $backgroundColor = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $backgroundColor);
        imagejpeg($image, $path);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFilePath)) {
            unlink($this->tempFilePath);
        }
        Mockery::close();
    }
}

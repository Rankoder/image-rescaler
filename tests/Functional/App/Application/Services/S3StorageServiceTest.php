<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\ValueObjects\ImageValueObject;
use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Mockery;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class S3StorageServiceTest extends TestCase
{
    private S3StorageService $service;
    private $s3ClientMock;
    private string $mockStoragePath;

    protected function setUp(): void
    {
        $this->s3ClientMock = Mockery::mock(S3Client::class);
        $this->service = $this->createServiceWithMockedS3Client();

        $this->mockStoragePath = sys_get_temp_dir() . '/mock_storage/';
        if (!file_exists($this->mockStoragePath)) {
            mkdir($this->mockStoragePath, 0777, true);
        }
    }

    private function createServiceWithMockedS3Client(): S3StorageService
    {
        $service = new S3StorageService();

        $reflection = new \ReflectionClass($service);
        $s3ClientProperty = $reflection->getProperty('s3Client');
        $s3ClientProperty->setValue($service, $this->s3ClientMock);

        $bucketProperty = $reflection->getProperty('bucket');
        $bucketProperty->setValue($service, 'image-rescaler');

        return $service;
    }

    #[DataProvider('imageProvider')]
    public function testUpload(string $filename, array $expected, array $putObjectReturn, array $headObjectReturns): void
    {
        $image = $this->createTestImage($filename);

        $this->s3ClientMock->shouldReceive('putObject')
            ->once()
            ->andReturn($putObjectReturn);

        foreach ($headObjectReturns as $headObjectReturn) {
            $mock = $this->s3ClientMock->shouldReceive('headObject')
                ->with(['Bucket' => 'image-rescaler', 'Key' => $headObjectReturn['key']]);
            if ($headObjectReturn['throw']) {
                $mock->andThrow(new AwsException('File not found', new \Aws\Command('headObject')));
            } else {
                $mock->andReturn([]);
            }
        }

        $result = $this->service->upload($image);

        $this->assertSame($expected['filename'], $result);

        $this->cleanupTestImage($image->getPath());
    }

    public static function imageProvider(): array
    {
        return [
            'first upload' => [
                'test_image.jpg',
                ['filename' => 'test_image_v1.jpg'],
                ['ObjectURL' => 'https://s3.amazonaws.com/bucket/test_image_v1.jpg'],
                [['key' => 'test_image_v1.jpg', 'throw' => true]]
            ],
            'second upload' => [
                'test_image.jpg',
                ['filename' => 'test_image_v2.jpg'],
                ['ObjectURL' => 'https://s3.amazonaws.com/bucket/test_image_v2.jpg'],
                [
                    ['key' => 'test_image_v1.jpg', 'throw' => false],
                    ['key' => 'test_image_v2.jpg', 'throw' => true]
                ]
            ],
        ];
    }

    private function createTestImage(string $filename): ImageValueObject
    {
        $imagePath = $this->mockStoragePath . $filename;
        file_put_contents($imagePath, 'test content');
        return new ImageValueObject($imagePath, $filename);
    }

    private function cleanupTestImage(string $imagePath): void
    {
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->mockStoragePath)) {
            array_map('unlink', glob("$this->mockStoragePath/*.*"));
            rmdir($this->mockStoragePath);
        }
        Mockery::close();
    }
}

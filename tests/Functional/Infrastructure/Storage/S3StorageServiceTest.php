<?php

declare(strict_types=1);

namespace Tests\Functional\Infrastructure\Storage;

use App\Domain\ValueObjects\ImageValueObject;
use App\Infrastructure\Storage\S3StorageService;
use Aws\Command;
use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestFiles;

final class S3StorageServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private TestFiles $files;
    private S3Client&MockInterface $client;
    private S3StorageService $service;

    protected function setUp(): void
    {
        $this->files = new TestFiles();
        $this->client = Mockery::mock(S3Client::class);
        $this->service = new S3StorageService($this->client, 'images', 'eu-central-1');
    }

    protected function tearDown(): void
    {
        $this->files->remove();
    }

    public function testPutsTheFileUnderTheFirstFreeVersion(): void
    {
        $source = $this->files->withContent('image', 'thumb.jpg');
        $this->client->shouldReceive('headObject')->with(['Bucket' => 'images', 'Key' => 'thumb_v1.jpg'])->andReturn([]);
        $this->client->shouldReceive('headObject')->with(['Bucket' => 'images', 'Key' => 'thumb_v2.jpg'])->andThrow($this->notFound());
        $this->client->shouldReceive('putObject')->once()->with(['Bucket' => 'images', 'Key' => 'thumb_v2.jpg', 'SourceFile' => $source]);

        $this->assertSame('thumb_v2.jpg', $this->service->upload(new ImageValueObject($source, 'thumb.jpg')));
        $this->assertFileDoesNotExist($source);
    }

    public function testAFailedPutIsReportedWithTheCause(): void
    {
        $source = $this->files->withContent('image', 'thumb.jpg');
        $this->client->shouldReceive('headObject')->andThrow($this->notFound());
        $failure = new AwsException('Access denied', new Command('putObject'));
        $this->client->shouldReceive('putObject')->andThrow($failure);

        try {
            $this->service->upload(new ImageValueObject($source, 'thumb.jpg'));
            $this->fail('The upload did not fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame($failure, $exception->getPrevious());
        }
    }

    public function testThePublicUrlNamesTheBucketAndRegion(): void
    {
        $this->assertSame('https://images.s3.eu-central-1.amazonaws.com/thumb_v1.jpg', $this->service->getPublicUrl('thumb_v1.jpg'));
    }

    private function notFound(): AwsException
    {
        return new AwsException('Not found', new Command('headObject'));
    }
}

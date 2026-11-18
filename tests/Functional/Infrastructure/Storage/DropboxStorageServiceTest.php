<?php

declare(strict_types=1);

namespace Tests\Functional\Infrastructure\Storage;

use App\Domain\ValueObjects\ImageValueObject;
use App\Infrastructure\Storage\DropboxStorageService;
use Kunnu\Dropbox\Dropbox;
use Kunnu\Dropbox\DropboxFile;
use Kunnu\Dropbox\Exceptions\DropboxClientException;
use Kunnu\Dropbox\Models\FileMetadata;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestFiles;

final class DropboxStorageServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const string NOT_FOUND = '{"error_summary": "path/not_found/..", "error": {".tag": "path"}}';

    private TestFiles $files;
    private Dropbox&MockInterface $dropbox;
    private DropboxStorageService $service;

    protected function setUp(): void
    {
        $this->files = new TestFiles();
        $this->dropbox = Mockery::mock(Dropbox::class);
        $this->service = new DropboxStorageService($this->dropbox, '/Apps/thumbnails');
    }

    protected function tearDown(): void
    {
        $this->files->remove();
    }

    public function testUploadsToTheConfiguredDirectoryUnderTheFirstFreeVersion(): void
    {
        $source = $this->files->withContent('image', 'thumb.jpg');
        $this->dropbox->shouldReceive('getMetadata')->with('/Apps/thumbnails/thumb_v1.jpg')->andReturn(new FileMetadata([]));
        $this->dropbox->shouldReceive('getMetadata')->with('/Apps/thumbnails/thumb_v2.jpg')->andThrow(new DropboxClientException(self::NOT_FOUND, 409));
        $this->dropbox->shouldReceive('upload')
            ->once()
            ->with(Mockery::type(DropboxFile::class), '/Apps/thumbnails/thumb_v2.jpg', ['autorename' => true])
            ->andReturn(new FileMetadata([]));

        $this->assertSame('thumb_v2.jpg', $this->service->upload(new ImageValueObject($source, 'thumb.jpg')));
        $this->assertFileDoesNotExist($source);
    }

    public function testAnErrorOtherThanNotFoundIsNotTakenForAFreeName(): void
    {
        $source = $this->files->withContent('image', 'thumb.jpg');
        $cause = new DropboxClientException('{"error_summary": "invalid_access_token/"}', 401);
        $this->dropbox->shouldReceive('getMetadata')->andThrow($cause);
        $this->dropbox->shouldNotReceive('upload');

        try {
            $this->service->upload(new ImageValueObject($source, 'thumb.jpg'));
            $this->fail('The upload did not fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame($cause, $exception->getPrevious());
            $this->assertFileDoesNotExist($source);
        }
    }

    public function testAFailedUploadKeepsItsCause(): void
    {
        $source = $this->files->withContent('image', 'thumb.jpg');
        $cause = new DropboxClientException('{"error_summary": "insufficient_space/"}', 409);
        $this->dropbox->shouldReceive('getMetadata')->andThrow(new DropboxClientException(self::NOT_FOUND, 409));
        $this->dropbox->shouldReceive('upload')->andThrow($cause);

        try {
            $this->service->upload(new ImageValueObject($source, 'thumb.jpg'));
            $this->fail('The upload did not fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame($cause, $exception->getPrevious());
        }
    }
}

<?php

declare(strict_types=1);

namespace Tests\Functional\Infrastructure\Storage;

use App\Domain\ValueObjects\ImageValueObject;
use App\Infrastructure\Storage\DropboxStorageService;
use Kunnu\Dropbox\Dropbox;
use Kunnu\Dropbox\DropboxFile;
use Kunnu\Dropbox\DropboxResponse;
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

    private const string CONFLICT = '{"error_summary": "path/conflict/file/..", "error": {".tag": "path", "reason": {".tag": "conflict"}}}';

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

    public function testAddsToTheConfiguredFolderAndMovesOnToTheNextVersionOnAConflict(): void
    {
        $source = $this->files->withContent('image', 'thumb.jpg');
        $this->dropbox->shouldReceive('upload')
            ->once()
            ->with(Mockery::type(DropboxFile::class), '/Apps/thumbnails/thumb_v1.jpg', ['mode' => 'add', 'autorename' => false])
            ->andThrow(new DropboxClientException(self::CONFLICT, 409));
        $this->dropbox->shouldReceive('upload')
            ->once()
            ->with(Mockery::type(DropboxFile::class), '/Apps/thumbnails/thumb_v2.jpg', ['mode' => 'add', 'autorename' => false])
            ->andReturn(new FileMetadata([]));

        $this->assertSame('thumb_v2.jpg', $this->service->upload(new ImageValueObject($source, 'thumb.jpg')));
        $this->assertFileDoesNotExist($source);
    }

    public function testAnErrorOtherThanAConflictFailsWithItsCause(): void
    {
        $source = $this->files->withContent('image', 'thumb.jpg');
        $cause = new DropboxClientException('{"error_summary": "invalid_access_token/"}', 401);
        $this->dropbox->shouldReceive('upload')->once()->andThrow($cause);

        try {
            $this->service->upload(new ImageValueObject($source, 'thumb.jpg'));
            $this->fail('The upload did not fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame($cause, $exception->getPrevious());
            $this->assertFileDoesNotExist($source);
        }
    }

    public function testThePublicUrlIsANewSharedLinkToTheFile(): void
    {
        $this->dropbox->shouldReceive('postToAPI')
            ->once()
            ->with('/sharing/create_shared_link_with_settings', ['path' => '/Apps/thumbnails/thumb_v1.jpg'])
            ->andReturn($this->response(['url' => 'https://www.dropbox.com/s/abc/thumb_v1.jpg?dl=0']));

        $this->assertSame('https://www.dropbox.com/s/abc/thumb_v1.jpg?dl=0', $this->service->getPublicUrl('thumb_v1.jpg'));
    }

    public function testAnExistingSharedLinkIsReused(): void
    {
        $this->dropbox->shouldReceive('postToAPI')
            ->with('/sharing/create_shared_link_with_settings', Mockery::any())
            ->andThrow(new DropboxClientException('{"error_summary": "shared_link_already_exists/.."}', 409));
        $this->dropbox->shouldReceive('postToAPI')
            ->with('/sharing/list_shared_links', ['path' => '/Apps/thumbnails/thumb_v1.jpg', 'direct_only' => true])
            ->andReturn($this->response(['links' => [['url' => 'https://www.dropbox.com/s/old/thumb_v1.jpg?dl=0']]]));

        $this->assertSame('https://www.dropbox.com/s/old/thumb_v1.jpg?dl=0', $this->service->getPublicUrl('thumb_v1.jpg'));
    }

    public function testAFailedLinkKeepsItsCause(): void
    {
        $cause = new DropboxClientException('{"error_summary": "missing_scope/"}', 401);
        $this->dropbox->shouldReceive('postToAPI')->andThrow($cause);

        try {
            $this->service->getPublicUrl('thumb_v1.jpg');
            $this->fail('No exception was thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame($cause, $exception->getPrevious());
        }
    }

    /**
     * @param array<string, mixed> $body
     */
    private function response(array $body): DropboxResponse&MockInterface
    {
        $response = Mockery::mock(DropboxResponse::class);
        $response->shouldReceive('getDecodedBody')->andReturn($body);

        return $response;
    }
}

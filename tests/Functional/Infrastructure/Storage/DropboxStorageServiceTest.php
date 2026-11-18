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
use Tests\Support\TestFiles;

final class DropboxStorageServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private TestFiles $files;
    private Dropbox&MockInterface $dropbox;

    protected function setUp(): void
    {
        $this->files = new TestFiles();
        $this->dropbox = Mockery::mock(Dropbox::class);
    }

    protected function tearDown(): void
    {
        $this->files->remove();
    }

    public function testUploadsUnderTheFirstFreeVersion(): void
    {
        $source = $this->files->withContent('image', 'thumb.jpg');
        $this->dropbox->shouldReceive('getMetadata')->with('/Aplikacje/image-rescaler/thumb_v1.jpg')->andReturn(new FileMetadata([]));
        $this->dropbox->shouldReceive('getMetadata')->with('/Aplikacje/image-rescaler/thumb_v2.jpg')->andThrow(new DropboxClientException('not found'));
        $this->dropbox->shouldReceive('upload')
            ->once()
            ->with(Mockery::type(DropboxFile::class), '/Aplikacje/image-rescaler/thumb_v2.jpg', ['autorename' => true])
            ->andReturn(new FileMetadata([]));

        $result = (new DropboxStorageService($this->dropbox))->upload(new ImageValueObject($source, 'thumb.jpg'));

        $this->assertSame('thumb_v2.jpg', $result);
    }
}

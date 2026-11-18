<?php

declare(strict_types=1);

namespace Tests\Functional\Infrastructure\Image;

use App\Domain\ValueObjects\ImageInfo;
use App\Infrastructure\Image\LocalImageInspector;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestFiles;

final class LocalImageInspectorTest extends TestCase
{
    private TestFiles $files;
    private LocalImageInspector $inspector;

    protected function setUp(): void
    {
        $this->files = new TestFiles();
        $this->inspector = new LocalImageInspector();
    }

    protected function tearDown(): void
    {
        $this->files->remove();
    }

    public function testReadsTypeAndDimensionsFromTheContentNotTheName(): void
    {
        $this->assertEquals(new ImageInfo('image/jpeg', 30, 20), $this->inspector->inspect($this->files->jpeg(30, 20, 'image.png')));
        $this->assertEquals(new ImageInfo('image/png', 30, 20), $this->inspector->inspect($this->files->png(30, 20, 'image.jpg')));
        $this->assertEquals(new ImageInfo('image/webp', 30, 20), $this->inspector->inspect($this->files->webp(30, 20, 'image')));
    }

    public function testAFileThatIsNotAnImageHasNoImageInfo(): void
    {
        $this->assertNull($this->inspector->inspect($this->files->withContent('<?php echo 1;', 'shell.jpg')));
        $this->assertNull($this->inspector->inspect($this->files->withContent('', 'empty.jpg')));
    }

    public function testMeasuresTheSize(): void
    {
        $this->assertSame(5, $this->inspector->sizeInBytes($this->files->withContent('12345', 'five')));
    }

    public function testAMissingFileCannotBeMeasured(): void
    {
        $this->expectException(RuntimeException::class);

        $this->inspector->sizeInBytes($this->files->directory() . '/missing');
    }
}

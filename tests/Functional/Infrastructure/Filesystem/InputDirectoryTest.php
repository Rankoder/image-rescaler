<?php

declare(strict_types=1);

namespace Tests\Functional\Infrastructure\Filesystem;

use App\Application\Validation\ErrorCode;
use App\Application\Validation\SourceFile;
use App\Domain\Enums\ImageAddOutcome;
use App\Infrastructure\Filesystem\ExclusiveCopy;
use App\Infrastructure\Filesystem\InputDirectory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestFiles;

final class InputDirectoryTest extends TestCase
{
    private TestFiles $outside;
    private TestFiles $input;

    protected function setUp(): void
    {
        $this->outside = new TestFiles();
        $this->input = new TestFiles();
    }

    protected function tearDown(): void
    {
        $this->input->remove();
        $this->outside->remove();
    }

    public function testFindsAFileInTheDirectory(): void
    {
        $path = $this->input->jpeg(50, 50, 'photo.jpg');

        $this->assertEquals(
            SourceFile::at((string) realpath($path), 'photo.jpg'),
            (new InputDirectory($this->input->directory(), new ExclusiveCopy()))->sourceFile('photo.jpg'),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function namesOutside(): iterable
    {
        yield 'parent directory' => ['../secret.jpg'];
        yield 'parent directory in the middle' => ['sub/../../secret.jpg'];
        yield 'absolute path' => ['/etc/passwd'];
    }

    #[DataProvider('namesOutside')]
    public function testRejectsANameThatLeadsOutside(string $name): void
    {
        $this->assertSame(ErrorCode::OutsideInputDirectory, (new InputDirectory($this->input->directory(), new ExclusiveCopy()))->sourceFile($name)->problem);
    }

    public function testRejectsASymlinkThatPointsOutside(): void
    {
        $target = $this->outside->jpeg(50, 50, 'secret.jpg');
        symlink($target, $this->input->directory() . '/link.jpg');

        $this->assertSame(ErrorCode::OutsideInputDirectory, (new InputDirectory($this->input->directory(), new ExclusiveCopy()))->sourceFile('link.jpg')->problem);
    }

    public function testAMissingFileIsNotFound(): void
    {
        $this->assertSame(ErrorCode::NotFound, (new InputDirectory($this->input->directory(), new ExclusiveCopy()))->sourceFile('missing.jpg')->problem);
    }

    public function testADirectoryIsNotAFile(): void
    {
        mkdir($this->input->directory() . '/sub.jpg');

        $problem = (new InputDirectory($this->input->directory(), new ExclusiveCopy()))->sourceFile('sub.jpg')->problem;
        rmdir($this->input->directory() . '/sub.jpg');

        $this->assertSame(ErrorCode::NotFound, $problem);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonImageNames(): iterable
    {
        yield 'hidden file' => ['.0123-hidden.jpg'];
        yield 'other extension' => ['notes.txt'];
        yield 'file with spaces' => ['my photo.jpg'];
        yield 'file in a subdirectory' => ['sub/photo.jpg'];
    }

    #[DataProvider('nonImageNames')]
    public function testRejectsAFileWhoseNameIsNotAPlainImageName(string $name): void
    {
        mkdir($this->input->directory() . '/sub');
        $this->input->jpeg(50, 50, '.0123-hidden.jpg');
        $this->input->withContent('notes', 'notes.txt');
        $this->input->jpeg(50, 50, 'my photo.jpg');
        $this->input->jpeg(50, 50, 'sub/photo.jpg');

        $problem = (new InputDirectory($this->input->directory(), new ExclusiveCopy()))->sourceFile($name)->problem;
        unlink($this->input->directory() . '/sub/photo.jpg');
        rmdir($this->input->directory() . '/sub');

        $this->assertSame(ErrorCode::NotFound, $problem);
    }

    public function testNothingIsFoundWhenTheDirectoryDoesNotExist(): void
    {
        $this->assertSame(ErrorCode::NotFound, (new InputDirectory($this->input->directory() . '/missing', new ExclusiveCopy()))->sourceFile('photo.jpg')->problem);
    }

    public function testListsOnlyPlainImageNamesSorted(): void
    {
        $this->input->png(10, 10, 'b.png');
        $this->input->jpeg(10, 10, 'a.jpg');
        $this->input->withContent('partial', '.0123-c.jpg');
        $this->input->withContent('notes', 'notes.txt');
        mkdir($this->input->directory() . '/sub.jpg');

        $names = (new InputDirectory($this->input->directory(), new ExclusiveCopy()))->names();
        rmdir($this->input->directory() . '/sub.jpg');

        $this->assertSame(['a.jpg', 'b.png'], $names);
    }

    public function testAddsAFileUnlessTheNameIsTaken(): void
    {
        $directory = new InputDirectory($this->input->directory(), new ExclusiveCopy());
        $first = $this->outside->withContent('first', 'first.jpg');
        $second = $this->outside->withContent('second', 'second.jpg');

        $this->assertTrue($directory->addIfAbsent($first, 'photo.jpg'));
        $this->assertFalse($directory->addIfAbsent($second, 'photo.jpg'));
        $this->assertStringEqualsFile($this->input->directory() . '/photo.jpg', 'first');
    }

    public function testEnforcesTheImageLimitWhenAdding(): void
    {
        $directory = new InputDirectory($this->input->directory(), new ExclusiveCopy());
        $first = $this->outside->withContent('first', 'first.jpg');
        $second = $this->outside->withContent('second', 'second.jpg');
        $third = $this->outside->withContent('third', 'third.jpg');

        $this->assertSame(ImageAddOutcome::Added, $directory->addWithinLimit($first, 'one.jpg', 2));
        $this->assertSame(ImageAddOutcome::Added, $directory->addWithinLimit($second, 'two.jpg', 2));
        $this->assertSame(ImageAddOutcome::LimitReached, $directory->addWithinLimit($third, 'three.jpg', 2));
        $this->assertSame(['one.jpg', 'two.jpg'], $directory->names());
        $this->assertFileDoesNotExist($first);
        $this->assertFileDoesNotExist($second);
        $this->assertFileDoesNotExist($third);
    }

    public function testRemovesTheTemporaryFileEvenWhenStoringFails(): void
    {
        $temporary = $this->outside->withContent('image', 'temp.jpg');
        $blocked = $this->outside->withContent('a file where the directory should be', 'blocked');
        $directory = new InputDirectory($blocked . '/input', new ExclusiveCopy());

        try {
            @$directory->addWithinLimit($temporary, 'photo.jpg', 10);
            $this->fail('Storing into a path blocked by a file succeeded.');
        } catch (RuntimeException) {
            $this->assertFileDoesNotExist($temporary);
        }
    }
}

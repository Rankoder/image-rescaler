<?php

declare(strict_types=1);

namespace Tests\Functional\Infrastructure\Cli;

use App\Application\Validation\ErrorCode;
use App\Application\Validation\SourceFile;
use App\Infrastructure\Cli\InputDirectory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
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
            (new InputDirectory($this->input->directory()))->sourceFile('photo.jpg'),
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
        $this->assertSame(ErrorCode::OutsideInputDirectory, (new InputDirectory($this->input->directory()))->sourceFile($name)->problem);
    }

    public function testRejectsASymlinkThatPointsOutside(): void
    {
        $target = $this->outside->jpeg(50, 50, 'secret.jpg');
        symlink($target, $this->input->directory() . '/link.jpg');

        $this->assertSame(ErrorCode::OutsideInputDirectory, (new InputDirectory($this->input->directory()))->sourceFile('link.jpg')->problem);
    }

    public function testAMissingFileIsNotFound(): void
    {
        $this->assertSame(ErrorCode::NotFound, (new InputDirectory($this->input->directory()))->sourceFile('missing.jpg')->problem);
    }

    public function testADirectoryIsNotAFile(): void
    {
        mkdir($this->input->directory() . '/sub');

        $problem = (new InputDirectory($this->input->directory()))->sourceFile('sub')->problem;
        rmdir($this->input->directory() . '/sub');

        $this->assertSame(ErrorCode::NotFound, $problem);
    }

    public function testNothingIsFoundWhenTheDirectoryDoesNotExist(): void
    {
        $this->assertSame(ErrorCode::NotFound, (new InputDirectory($this->input->directory() . '/missing'))->sourceFile('photo.jpg')->problem);
    }
}

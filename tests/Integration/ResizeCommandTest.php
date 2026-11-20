<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Filesystem\ExclusiveCopy;
use App\Infrastructure\Filesystem\InputDirectory;
use App\Infrastructure\Cli\ResizeCommand;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestFiles;
use Tests\Support\Wiring;

final class ResizeCommandTest extends TestCase
{
    private const STORAGE_DIR = __DIR__ . '/../../storage/';

    private TestFiles $files;
    private string $outputName;

    protected function setUp(): void
    {
        $this->files = new TestFiles();
        $this->outputName = uniqid('cli_test_');
    }

    protected function tearDown(): void
    {
        $this->files->remove();
        foreach (glob(self::STORAGE_DIR . $this->outputName . '*') ?: [] as $stored) {
            unlink($stored);
        }
    }

    public function testStoresTheImageCroppedToTheFrame(): void
    {
        $this->files->png(150, 150, 'photo.png');

        [$exitCode, $output, $errors] = $this->runCommand(['f' => 'photo.png', 'w' => '60', 'h' => '40', 'o' => $this->outputName]);

        $this->assertSame([0, "Stored {$this->outputName}_v1.png\n", ''], [$exitCode, $output, $errors]);
        $size = getimagesize(self::STORAGE_DIR . $this->outputName . '_v1.png');
        $this->assertNotFalse($size);
        $this->assertSame([60, 40], [$size[0], $size[1]]);
    }

    public function testGoesThroughTheSameValidationAsTheApiAndReportsEveryError(): void
    {
        $this->files->withContent('<?php echo 1;', 'photo.jpg');

        [$exitCode, $output, $errors] = $this->runCommand(['f' => 'photo.jpg', 'w' => '39', 's' => 'ftp', 'o' => $this->outputName]);

        $this->assertSame(1, $exitCode);
        $this->assertSame('', $output);
        $this->assertSame(
            "-f: The file is not a JPEG, PNG or WebP image.\n"
            . "-w: max_width must be between 40 and 5000.\n"
            . "-h: max_height is required.\n"
            . "-s: service must be one of: local, s3, dropbox.\n"
            . ResizeCommand::USAGE . "\n",
            $errors,
        );
        $this->assertSame([], glob(self::STORAGE_DIR . $this->outputName . '*'));
    }

    public function testReadsOnlyFromTheInputDirectory(): void
    {
        [$exitCode, , $errors] = $this->runCommand(['f' => '../../etc/passwd', 'w' => '60', 'h' => '40']);

        $this->assertSame(1, $exitCode);
        $this->assertStringStartsWith("-f: The file must be inside the input directory.\n", $errors);
    }

    public function testRejectsAFileWhoseNameIsNotAPlainImageInTheDirectory(): void
    {
        $this->files->jpeg(150, 150, '.0123-hidden.jpg');

        [$exitCode, , $errors] = $this->runCommand(['f' => '.0123-hidden.jpg', 'w' => '60', 'h' => '40']);

        $this->assertSame(1, $exitCode);
        $this->assertStringStartsWith("-f: The image does not exist in the input directory.\n", $errors);
    }

    /**
     * @param array<string, string> $options
     * @return array{int, string, string}
     */
    private function runCommand(array $options): array
    {
        $output = fopen('php://memory', 'w+');
        $errors = fopen('php://memory', 'w+');
        $this->assertNotFalse($output);
        $this->assertNotFalse($errors);

        $exitCode = (new ResizeCommand(Wiring::resizeAndStoreImage(), new InputDirectory($this->files->directory(), new ExclusiveCopy())))->run($options, $output, $errors);

        rewind($output);
        rewind($errors);

        return [$exitCode, (string) stream_get_contents($output), (string) stream_get_contents($errors)];
    }
}

<?php

declare(strict_types=1);

namespace Tests\Functional;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestFiles;

/**
 * Attempts to break the upload, sent through routes.php the way PHP hands over a multipart request.
 */
final class UploadAttemptsTest extends TestCase
{
    private const STORAGE_DIR = __DIR__ . '/../../storage/';
    private const PUBLIC_DIR = __DIR__ . '/../../public/';

    private TestFiles $files;
    /** @var list<string> */
    private array $storageBefore;
    /** @var list<string> */
    private array $publicBefore;

    protected function setUp(): void
    {
        $this->files = new TestFiles();
        $this->storageBefore = $this->listing(self::STORAGE_DIR);
        $this->publicBefore = $this->listing(self::PUBLIC_DIR);
    }

    protected function tearDown(): void
    {
        $this->files->remove();
        foreach (array_diff($this->listing(self::STORAGE_DIR), $this->storageBefore) as $stored) {
            unlink(self::STORAGE_DIR . $stored);
        }
    }

    #[RunInSeparateProcess]
    public function testAPhpScriptNamedLikeAnImageIsRejected(): void
    {
        $script = $this->files->withContent('<?php system($_GET["c"]);', 'shell.jpg');

        $this->assertRejected('unsupported_type', $this->post($script, 'shell.jpg'));
    }

    #[RunInSeparateProcess]
    public function testAGifHeaderInFrontOfAScriptIsRejected(): void
    {
        $polyglot = $this->files->withContent("GIF89a\x01\x00\x01\x00<?php system(\$_GET['c']); ?>", 'shell.jpg');

        $this->assertRejected('unsupported_type', $this->post($polyglot, 'shell.jpg'));
    }

    #[RunInSeparateProcess]
    public function testARealImageNamedAsAScriptIsRejected(): void
    {
        $this->assertRejected('extension_mismatch', $this->post($this->files->jpeg(150, 150), 'shell.php'));
    }

    #[RunInSeparateProcess]
    public function testAnEmptyFileIsRejected(): void
    {
        $this->assertRejected('empty', $this->post($this->files->withContent('', 'empty.jpg'), 'empty.jpg'));
    }

    #[RunInSeparateProcess]
    public function testAFileOverTheLimitIsRejected(): void
    {
        $this->assertRejected('too_large', $this->post($this->files->oversizedJpeg(5 * 1024 * 1024), 'big.jpg'));
    }

    #[RunInSeparateProcess]
    public function testAnOutputFilenameLeadingOutOfStorageIsRejected(): void
    {
        $response = $this->post($this->files->jpeg(150, 150), 'photo.jpg', ['output_filename' => '../public/shell']);

        $this->assertRejected('invalid_characters', $response, 'output_filename');
    }

    #[RunInSeparateProcess]
    public function testAClientFilenameLeadingOutOfStorageOnlyShapesASafeNameInStorage(): void
    {
        $response = $this->post($this->files->jpeg(150, 150), '../public/shell.jpg');

        $this->assertSame('success', $response['status']);
        $this->assertIsString($response['path']);
        $this->assertMatchesRegularExpression('/^resized_shell_[a-z0-9]{13}_v1\.jpg$/', $response['path']);
        $this->assertFileExists(self::STORAGE_DIR . $response['path']);
        $this->assertSame($this->publicBefore, $this->listing(self::PUBLIC_DIR));
    }

    /**
     * @param array<string, mixed> $response
     */
    private function assertRejected(string $code, array $response, string $field = 'file'): void
    {
        $this->assertSame(['status' => 'error', 'errors' => [['field' => $field, 'code' => $code]]], [
            'status' => $response['status'] ?? null,
            'errors' => array_map(
                static fn(mixed $error): array => is_array($error) ? ['field' => $error['field'] ?? null, 'code' => $error['code'] ?? null] : [],
                is_array($response['errors'] ?? null) ? $response['errors'] : [],
            ),
        ]);
        $this->assertSame($this->storageBefore, $this->listing(self::STORAGE_DIR));
        $this->assertSame($this->publicBefore, $this->listing(self::PUBLIC_DIR));
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, mixed>
     */
    private function post(string $source, string $clientFilename, array $fields = []): array
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/upload';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['CONTENT_TYPE'] = 'multipart/form-data; boundary=attempt';
        $_POST = $fields + ['max_width' => '60', 'max_height' => '40'];
        $_FILES = ['file' => [
            'name' => $clientFilename,
            'type' => 'image/jpeg',
            'tmp_name' => $source,
            'error' => UPLOAD_ERR_OK,
            'size' => (int) filesize($source),
        ]];

        ob_start();
        try {
            require __DIR__ . '/../../routes.php';
        } finally {
            $output = (string) ob_get_clean();
        }

        $response = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($response);

        /** @var array<string, mixed> $response */
        return $response;
    }

    /**
     * @return list<string>
     */
    private function listing(string $directory): array
    {
        return array_values(array_diff(scandir($directory) ?: [], ['.', '..']));
    }
}

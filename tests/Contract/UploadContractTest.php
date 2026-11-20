<?php

declare(strict_types=1);

namespace Tests\Contract;

use App\Domain\Interfaces\StorageRegistry;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;
use Tests\Support\TestFiles;
use Tests\Support\Wiring;

/**
 * One case per rule of openapi.yaml; every response is also checked against the contract.
 */
final class UploadContractTest extends TestCase
{
    use MatchesContract;
    use MockeryPHPUnitIntegration;

    private const STORAGE_DIR = __DIR__ . '/../../storage/';
    private const MULTIPART = 'multipart/form-data; boundary=contract';

    private TestFiles $files;
    private string $outputName;

    protected function setUp(): void
    {
        $this->files = new TestFiles();
        $this->outputName = uniqid('contract_test_');
    }

    protected function tearDown(): void
    {
        $this->files->remove();
        foreach (glob(self::STORAGE_DIR . $this->outputName . '*') ?: [] as $stored) {
            unlink($stored);
        }
    }

    /**
     * @return iterable<string, array{'jpeg'|'png'|'webp', int, string}>
     */
    public static function formats(): iterable
    {
        yield 'JPEG' => ['jpeg', IMAGETYPE_JPEG, 'jpg'];
        yield 'PNG' => ['png', IMAGETYPE_PNG, 'png'];
        yield 'WebP' => ['webp', IMAGETYPE_WEBP, 'webp'];
    }

    /**
     * @param 'jpeg'|'png'|'webp' $format
     */
    #[DataProvider('formats')]
    public function testCropsTheImageToExactlyTheFrameKeepingItsFormat(string $format, int $imageType, string $extension): void
    {
        $source = match ($format) {
            'jpeg' => $this->files->jpeg(150, 150),
            'png' => $this->files->png(150, 150),
            'webp' => $this->files->webp(150, 150),
        };

        $response = $this->upload($source, 'photo.' . $extension, ['max_width' => '60', 'max_height' => '40']);

        $storedName = $this->outputName . '_v1.' . $extension;
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($storedName, $this->json($response)['path']);
        $size = getimagesize(self::STORAGE_DIR . $storedName);
        $this->assertNotFalse($size);
        $this->assertSame([60, 40, $imageType], [$size[0], $size[1], $size[2]]);
    }

    public function testSuccessResponseDoesNotEscapeSlashesInUrl(): void
    {
        $response = $this->upload($this->files->jpeg(150, 150), 'photo.jpg', ['max_width' => '60', 'max_height' => '40']);

        $storedName = $this->outputName . '_v1.jpg';
        $rawBody = (string) $response->getBody();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('"url":"http://localhost:8080/files/' . $storedName . '"', $rawBody);
        $this->assertStringNotContainsString('\/', $rawBody);
    }

    public function testASecondUploadUnderTheSameNameDoesNotOverwriteTheFirst(): void
    {
        $this->upload($this->files->jpeg(150, 150, 'first.jpg'), 'photo.jpg', ['max_width' => '60', 'max_height' => '40']);

        $response = $this->upload($this->files->jpeg(150, 150, 'second.jpg'), 'photo.jpg', ['max_width' => '60', 'max_height' => '40']);

        $this->assertSame($this->outputName . '_v2.jpg', $this->json($response)['path']);
    }

    public function testAClientFilenameWithAPathOnlyShapesASafeNameInStorage(): void
    {
        $publicDir = __DIR__ . '/../../public/';
        $publicBefore = scandir($publicDir);

        $response = $this->upload(
            $this->files->jpeg(150, 150),
            '../public/shell.jpg',
            ['max_width' => '60', 'max_height' => '40'],
            withOutputFilename: false,
        );

        $path = $this->json($response)['path'] ?? null;
        try {
            $this->assertSame(200, $response->getStatusCode());
            $this->assertIsString($path);
            $this->assertMatchesRegularExpression('/^resized_shell_[a-z0-9]{13}_v1\.jpg$/', $path);
            $this->assertFileExists(self::STORAGE_DIR . $path);
            $this->assertSame($publicBefore, scandir($publicDir));
        } finally {
            if (is_string($path) && is_file(self::STORAGE_DIR . $path)) {
                unlink(self::STORAGE_DIR . $path);
            }
        }
    }

    public function testARequestThatIsNotMultipartIsMalformed(): void
    {
        $request = (new ServerRequest('POST', '/upload', ['Content-Type' => 'application/json']))
            ->withParsedBody(['max_width' => '60', 'max_height' => '40']);

        $response = Wiring::imageController()->upload($request);

        $this->assertErrors(400, [[null, 'malformed_request']], $response);
    }

    public function testMissingRequiredFieldsAreAllReportedAtOnce(): void
    {
        $response = $this->send(new ServerRequest('POST', '/upload', ['Content-Type' => self::MULTIPART]));

        $this->assertErrors(422, [['file', 'required'], ['max_width', 'required'], ['max_height', 'required']], $response);
    }

    /**
     * @return iterable<string, array{'empty'|'oversized'|'gif'|'gif_polyglot'|'text'|'png'|'jpeg'|'too_wide'|'small', string, string}>
     */
    public static function rejectedFiles(): iterable
    {
        yield 'empty file' => ['empty', 'photo.jpg', 'empty'];
        yield 'over 5 MB' => ['oversized', 'photo.jpg', 'too_large'];
        yield 'GIF' => ['gif', 'photo.gif', 'unsupported_type'];
        yield 'GIF polyglot named .jpg' => ['gif_polyglot', 'shell.jpg', 'unsupported_type'];
        yield 'text named .jpg' => ['text', 'photo.jpg', 'unsupported_type'];
        yield 'PNG named .jpg' => ['png', 'photo.jpg', 'extension_mismatch'];
        yield 'JPEG without extension' => ['jpeg', 'photo', 'extension_mismatch'];
        yield 'over 5000 px wide' => ['too_wide', 'photo.png', 'dimensions_too_large'];
        yield 'smaller than the frame' => ['small', 'photo.jpg', 'smaller_than_frame'];
    }

    /**
     * @param 'empty'|'oversized'|'gif'|'gif_polyglot'|'text'|'png'|'jpeg'|'too_wide'|'small' $kind
     */
    #[DataProvider('rejectedFiles')]
    public function testRejectsTheFile(string $kind, string $clientFilename, string $code): void
    {
        $source = match ($kind) {
            'empty' => $this->files->withContent('', 'source'),
            'oversized' => $this->files->oversizedJpeg(5 * 1024 * 1024),
            'gif' => $this->files->gif(150, 150),
            'gif_polyglot' => $this->files->withContent("GIF89a\x01\x00\x01\x00<?php system(\$_GET['c']); ?>", 'shell.jpg'),
            'text' => $this->files->withContent('not an image', 'source'),
            'png' => $this->files->png(150, 150),
            'jpeg' => $this->files->jpeg(150, 150),
            'too_wide' => $this->files->png(5001, 50),
            'small' => $this->files->jpeg(50, 30),
        };

        $response = $this->upload($source, $clientFilename, ['max_width' => '60', 'max_height' => '40']);

        $this->assertErrors(422, [['file', $code]], $response);
        $this->assertSame([], glob(self::STORAGE_DIR . $this->outputName . '*'));
    }

    public function testAFileOverThePhpUploadLimitIsTooLarge(): void
    {
        $response = $this->upload($this->files->jpeg(150, 150), 'photo.jpg', ['max_width' => '60', 'max_height' => '40'], UPLOAD_ERR_INI_SIZE);

        $this->assertErrors(422, [['file', 'too_large']], $response);
    }

    public function testARequestOverThePhpPostLimitIsTooLargeRatherThanMissingEverything(): void
    {
        // PHP hands such a request over with no fields and no files
        $request = new ServerRequest('POST', '/upload', [
            'Content-Type' => self::MULTIPART,
            'Content-Length' => (string) (Wiring::config()->postMaxSize + 1),
        ]);

        $this->assertErrors(422, [['file', 'too_large']], $this->send($request));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function rejectedFrameSides(): iterable
    {
        yield 'not a number' => ['abc', 'not_integer'];
        yield 'below 40' => ['39', 'out_of_range'];
    }

    #[DataProvider('rejectedFrameSides')]
    public function testRejectsTheFrameWidth(string $value, string $code): void
    {
        $response = $this->upload($this->files->jpeg(150, 150), 'photo.jpg', ['max_width' => $value, 'max_height' => '40']);

        $this->assertErrors(422, [['max_width', $code]], $response);
    }

    #[DataProvider('rejectedFrameSides')]
    public function testRejectsTheFrameHeight(string $value, string $code): void
    {
        $response = $this->upload($this->files->jpeg(150, 150), 'photo.jpg', ['max_width' => '60', 'max_height' => $value]);

        $this->assertErrors(422, [['max_height', $code]], $response);
    }

    public function testAcceptsTheFrameLimits(): void
    {
        $response = $this->upload($this->files->jpeg(5000, 40), 'photo.jpg', ['max_width' => '5000', 'max_height' => '40']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertMatchesContract('post', '/upload', $response);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function rejectedOutputFilenames(): iterable
    {
        yield 'path traversal' => ['../../etc/passwd', 'invalid_characters'];
        yield 'with an extension' => ['thumbnail.jpg', 'invalid_characters'];
        yield 'with a space' => ['my thumbnail', 'invalid_characters'];
        yield 'over 100 characters' => [str_repeat('a', 101), 'too_long'];
        yield 'trailing newline' => ["thumbnail\n", 'invalid_characters'];
    }

    #[DataProvider('rejectedOutputFilenames')]
    public function testRejectsTheOutputFilename(string $outputFilename, string $code): void
    {
        $response = $this->upload($this->files->jpeg(150, 150), 'photo.jpg', [
            'max_width' => '60',
            'max_height' => '40',
            'output_filename' => $outputFilename,
        ]);

        $this->assertErrors(422, [['output_filename', $code]], $response);
    }

    public function testRejectsAnUnsupportedService(): void
    {
        $response = $this->upload($this->files->jpeg(150, 150), 'photo.jpg', [
            'max_width' => '60',
            'max_height' => '40',
            'service' => 'ftp',
        ]);

        $this->assertErrors(422, [['service', 'unsupported']], $response);
    }

    public function testErrorsOfDifferentFieldsComeTogether(): void
    {
        $response = $this->upload($this->files->gif(150, 150), 'photo.gif', [
            'max_width' => '39',
            'max_height' => 'abc',
            'service' => 'ftp',
            'output_filename' => '../x',
        ]);

        $this->assertErrors(422, [
            ['file', 'unsupported_type'],
            ['max_width', 'out_of_range'],
            ['max_height', 'not_integer'],
            ['service', 'unsupported'],
            ['output_filename', 'invalid_characters'],
        ], $response);
    }

    public function testAStorageFailureReturnsTheErrorEnvelope(): void
    {
        $failure = new RuntimeException('Storage offline');
        $storages = Mockery::mock(StorageRegistry::class);
        $storages->shouldReceive('get')->andThrow($failure);
        $logger = new class extends AbstractLogger {
            /** @var list<array{mixed, string, array<string, mixed>}> */
            public array $records = [];

            /**
             * @param array<string, mixed> $context
             */
            public function log(mixed $level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = [$level, (string) $message, $context];
            }
        };
        $controller = Wiring::imageController(Wiring::resizeAndStoreImage(storages: $storages), $logger);

        $factory = new Psr17Factory();
        $source = $this->files->jpeg(150, 150);
        $file = $factory->createUploadedFile(
            $factory->createStreamFromFile($source),
            (int) filesize($source),
            UPLOAD_ERR_OK,
            'photo.jpg',
        );
        $request = (new ServerRequest('POST', '/upload', ['Content-Type' => self::MULTIPART]))
            ->withUploadedFiles(['file' => $file])
            ->withParsedBody(['max_width' => '60', 'max_height' => '40']);

        $this->assertErrors(500, [[null, 'server_error']], $controller->upload($request));
        $this->assertSame([['error', 'Upload failed', ['exception' => $failure]]], $logger->records);
    }

    public function testMethodNotAllowedResponseMatchesTheContract(): void
    {
        $response = Wiring::container()->jsonResponses()->methodNotAllowed();

        $this->assertSame('GET, POST, HEAD', $response->getHeaderLine('Allow'));
        $this->assertErrors(405, [[null, 'method_not_allowed']], $response);
    }

    /**
     * @param array<string, string> $fields
     */
    private function upload(
        string $source,
        string $clientFilename,
        array $fields,
        int $uploadError = UPLOAD_ERR_OK,
        bool $withOutputFilename = true,
    ): ResponseInterface {
        $factory = new Psr17Factory();
        $file = $factory->createUploadedFile(
            $factory->createStreamFromFile($source),
            (int) filesize($source),
            $uploadError,
            $clientFilename,
        );

        $defaults = $withOutputFilename
            ? ['service' => 'local', 'output_filename' => $this->outputName]
            : ['service' => 'local'];

        $request = (new ServerRequest('POST', '/upload', ['Content-Type' => self::MULTIPART]))
            ->withUploadedFiles(['file' => $file])
            ->withParsedBody($fields + $defaults);

        return $this->send($request);
    }

    private function send(ServerRequest $request): ResponseInterface
    {
        $response = Wiring::imageController()->upload($request);
        $this->assertMatchesContract('post', '/upload', $response);

        return $response;
    }

    /**
     * @param list<array{?string, string}> $expected field and code of every error
     */
    private function assertErrors(int $status, array $expected, ResponseInterface $response): void
    {
        $this->assertMatchesContract('post', '/upload', $response);
        $this->assertSame($status, $response->getStatusCode());

        $errors = $this->json($response)['errors'] ?? null;
        $this->assertIsArray($errors);
        $actual = [];
        foreach ($errors as $error) {
            $this->assertIsArray($error);
            $actual[] = [$error['field'] ?? null, $error['code'] ?? null];
        }
        $this->assertEqualsCanonicalizing($expected, $actual);
    }

    /**
     * @return array<mixed>
     */
    private function json(ResponseInterface $response): array
    {
        $decoded = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        return $decoded;
    }
}

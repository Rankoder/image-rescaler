<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Application\Services\InputImages;
use App\Application\Validation\NameRule;
use App\Domain\Enums\ImageAddOutcome;
use App\Domain\Enums\ImageFormat;
use App\Domain\Enums\StorageType;
use App\Domain\Interfaces\ImageLibrary;
use App\Domain\Interfaces\ImageReencoder;
use App\Domain\Interfaces\StorageRegistry;
use App\Domain\Interfaces\StorageServiceInterface;
use App\Infrastructure\Controllers\FileController;
use App\Infrastructure\Controllers\HealthController;
use App\Infrastructure\Controllers\ImagesController;
use App\Infrastructure\Http\HttpKernel;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;
use Tests\Support\HttpTestHelpers;
use Tests\Support\TestFiles;
use Tests\Support\Wiring;
use Throwable;
use TypeError;

final class RoutesTest extends TestCase
{
    use HttpTestHelpers;

    private TestFiles $files;

    protected function setUp(): void
    {
        $this->files = new TestFiles();
    }

    protected function tearDown(): void
    {
        $this->files->remove();
    }

    #[RunInSeparateProcess]
    public function testRoutesScriptEmitsHealthResponse(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/health';
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        ob_start();
        try {
            require __DIR__ . '/../../routes.php';
        } finally {
            $output = (string) ob_get_clean();
        }

        $this->assertSame(200, http_response_code());
        $this->assertSame('{"status":"ok"}', $output);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function notFoundRoutes(): iterable
    {
        yield 'unknown path' => ['GET', '/nope'];
        yield 'POST /health' => ['POST', '/health'];
        yield 'GET /upload' => ['GET', '/upload'];
        yield 'POST /files/a.jpg' => ['POST', '/files/a.jpg'];
        yield 'trailing slash /health/' => ['GET', '/health/'];
        yield 'trailing slash /images/' => ['GET', '/images/'];
        yield 'trailing slash /upload/' => ['POST', '/upload/'];
        yield 'trailing slash /files/' => ['GET', '/files/'];
        yield 'trailing slash /files/thumb.jpg/' => ['GET', '/files/thumb.jpg/'];
        yield 'double slash //health' => ['GET', '//health'];
        yield 'double slash /files//thumb.jpg' => ['GET', '/files//thumb.jpg'];
    }

    #[DataProvider('notFoundRoutes')]
    public function testUnmatchedRoutesReturnNotFoundWithoutLoggingErrors(string $method, string $uri): void
    {
        $logger = $this->spyLogger();
        $response = Wiring::httpKernel(logger: $logger)->handle(new ServerRequest($method, $uri));

        $this->assertErrorEnvelope(404, [[null, 'not_found']], $response);
        $this->assertSame([], $logger->records);
    }

    public function testQueryStringIsIgnoredDuringRouting(): void
    {
        $this->files->jpeg(60, 40, 'thumb_v1.jpg');
        $kernel = $this->kernelWithDirectories($this->files->directory(), $this->files->directory());

        $health = $kernel->handle(new ServerRequest('GET', '/health?x=1'));
        $images = $kernel->handle(new ServerRequest('GET', '/images?a=b'));
        $file = $kernel->handle(new ServerRequest('GET', '/files/thumb_v1.jpg?v=1'));

        $this->assertSame(200, $health->getStatusCode());
        $this->assertSame(['status' => 'ok'], $this->json($health));
        $this->assertSame(200, $images->getStatusCode());
        $this->assertSame(['images' => ['thumb_v1.jpg']], $this->json($images));
        $this->assertSame([200, 'image/jpeg'], [$file->getStatusCode(), $file->getHeaderLine('Content-Type')]);
    }

    public function testHeadOnSuccessRoutesPreservesHeadersAndReturnsEmptyBody(): void
    {
        $this->files->jpeg(60, 40, 'thumb_v1.jpg');
        $kernel = $this->kernelWithDirectories($this->files->directory(), $this->files->directory());

        $health = $kernel->handle(new ServerRequest('HEAD', '/health'));
        $images = $kernel->handle(new ServerRequest('HEAD', '/images'));
        $file = $kernel->handle(new ServerRequest('HEAD', '/files/thumb_v1.jpg'));

        $this->assertSame(200, $health->getStatusCode());
        $this->assertSame('application/json', $health->getHeaderLine('Content-Type'));
        $this->assertSame('no-store', $health->getHeaderLine('Cache-Control'));
        $this->assertSame('', (string) $health->getBody());

        $this->assertSame(200, $images->getStatusCode());
        $this->assertSame('application/json', $images->getHeaderLine('Content-Type'));
        $this->assertSame('', (string) $images->getBody());

        $this->assertSame(200, $file->getStatusCode());
        $this->assertSame('image/jpeg', $file->getHeaderLine('Content-Type'));
        $this->assertSame('nosniff', $file->getHeaderLine('X-Content-Type-Options'));
        $this->assertSame('public, max-age=86400', $file->getHeaderLine('Cache-Control'));
        $this->assertSame('', (string) $file->getBody());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function headNotFoundUris(): iterable
    {
        yield 'missing file' => ['/files/missing.jpg'];
        yield 'POST-only route' => ['/upload'];
        yield 'unknown route' => ['/nieistnieje'];
    }

    #[DataProvider('headNotFoundUris')]
    public function testHeadOnNotFoundRoutesReturns404WithEmptyBody(string $uri): void
    {
        $response = Wiring::httpKernel()->handle(new ServerRequest('HEAD', $uri));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('', (string) $response->getBody());
    }

    public function testHeadOnServerErrorReturns500WithEmptyBodyAndLogsException(): void
    {
        $failure = new RuntimeException('Disk failed');
        $logger = $this->spyLogger();
        $this->files->jpeg(60, 40, 'thumb_v1.jpg');

        $imagesKernel = $this->failingKernel('GET /images', $failure, $logger);
        $imagesResponse = $imagesKernel->handle(new ServerRequest('HEAD', '/images'));

        $this->assertSame(500, $imagesResponse->getStatusCode());
        $this->assertSame('application/json', $imagesResponse->getHeaderLine('Content-Type'));
        $this->assertSame('', (string) $imagesResponse->getBody());

        $filesKernel = $this->failingKernel('GET /files/{name}', $failure, $logger);
        $filesResponse = $filesKernel->handle(new ServerRequest('HEAD', '/files/thumb_v1.jpg'));

        $this->assertSame(500, $filesResponse->getStatusCode());
        $this->assertSame('application/json', $filesResponse->getHeaderLine('Content-Type'));
        $this->assertSame('', (string) $filesResponse->getBody());
        $this->assertCount(2, $logger->records);
    }

    public function testKernelIsStatelessBetweenHeadAndGet(): void
    {
        $kernel = Wiring::httpKernel();

        $head = $kernel->handle(new ServerRequest('HEAD', '/health'));
        $get = $kernel->handle(new ServerRequest('GET', '/health'));

        $this->assertSame('', (string) $head->getBody());
        $this->assertSame('{"status":"ok"}', (string) $get->getBody());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unsupportedMethods(): iterable
    {
        yield 'PUT' => ['PUT', '/upload'];
        yield 'DELETE' => ['DELETE', '/images'];
        yield 'PATCH' => ['PATCH', '/upload'];
        yield 'OPTIONS' => ['OPTIONS', '/health'];
        yield 'TRACE' => ['TRACE', '/health'];
        yield 'custom FOO' => ['FOO', '/upload'];
        yield 'PUT on unknown route' => ['PUT', '/nieistnieje'];
        yield 'lowercase get' => ['get', '/health'];
        yield 'lowercase post' => ['post', '/upload'];
        yield 'lowercase head' => ['head', '/health'];
    }

    #[DataProvider('unsupportedMethods')]
    public function testUnsupportedMethodReturnsMethodNotAllowedWithAllowHeader(string $method, string $uri): void
    {
        $logger = $this->spyLogger();
        $response = Wiring::httpKernel(logger: $logger)->handle(new ServerRequest($method, $uri));

        $this->assertSame('GET, POST, HEAD', $response->getHeaderLine('Allow'));
        $this->assertErrorEnvelope(405, [[null, 'method_not_allowed']], $response);
        $this->assertSame([], $logger->records);
    }

    /**
     * @return iterable<string, array{string|list<string>}>
     */
    public static function disallowedMethodOverrides(): iterable
    {
        yield 'lowercase delete' => ['delete'];
        yield 'mixed-case Delete' => ['Delete'];
        yield 'padded PUT' => ['  PUT  '];
        yield 'OPTIONS' => ['OPTIONS'];
        yield 'TRACE' => ['TRACE'];
        yield 'FOO' => ['FOO'];
        yield 'GET' => ['GET'];
        yield 'comma-separated PUT, DELETE' => ['PUT, DELETE'];
        yield 'comma-separated POST, GET' => ['POST, GET'];
        yield 'multiple headers POST and DELETE' => [['POST', 'DELETE']];
    }

    /**
     * @param string|list<string> $override
     */
    #[DataProvider('disallowedMethodOverrides')]
    public function testPostWithDisallowedMethodOverrideReturns405(string|array $override): void
    {
        $logger = $this->spyLogger();
        $request = (new ServerRequest('POST', '/upload'))->withHeader('X-HTTP-Method-Override', $override);

        $response = Wiring::httpKernel(logger: $logger)->handle($request);

        $this->assertSame('GET, POST, HEAD', $response->getHeaderLine('Allow'));
        $this->assertErrorEnvelope(405, [[null, 'method_not_allowed']], $response);
        $this->assertSame([], $logger->records);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function acceptedMethodOverrides(): iterable
    {
        yield 'empty string' => [''];
        yield 'whitespace' => ['   '];
        yield 'POST' => ['POST'];
        yield 'lowercase post' => ['post'];
        yield 'padded post' => ['  post  '];
    }

    #[DataProvider('acceptedMethodOverrides')]
    public function testPostWithEmptyOrPostMethodOverrideProceedsAsPost(string $override): void
    {
        $request = $this->multipartRequest('/upload', contentType: 'application/json')
            ->withHeader('X-HTTP-Method-Override', $override);

        $response = Wiring::httpKernel()->handle($request);

        $this->assertErrorEnvelope(400, [[null, 'malformed_request']], $response);
    }

    public function testGetAndHeadIgnoreMethodOverrideHeader(): void
    {
        $kernel = Wiring::httpKernel();

        $get = $kernel->handle((new ServerRequest('GET', '/health'))->withHeader('X-HTTP-Method-Override', 'DELETE'));
        $head = $kernel->handle((new ServerRequest('HEAD', '/health'))->withHeader('X-HTTP-Method-Override', 'DELETE'));

        $this->assertSame(200, $get->getStatusCode());
        $this->assertSame(200, $head->getStatusCode());
        $this->assertSame('', (string) $head->getBody());
    }

    /**
     * @return iterable<string, array{'GET /health'|'GET /images'|'GET /files/{name}'|'POST /upload'|'POST /images', Throwable}>
     */
    public static function serverFailures(): iterable
    {
        $secret = '/var/www/storage/secret AKIAIOSFODNN7EXAMPLE';
        $routes = ['GET /health', 'GET /images', 'GET /files/{name}', 'POST /upload', 'POST /images'];

        foreach ($routes as $route) {
            yield $route . ' RuntimeException' => [$route, new RuntimeException($secret)];
            yield $route . ' TypeError' => [$route, new TypeError($secret)];
        }
    }

    /**
     * @param 'GET /health'|'GET /images'|'GET /files/{name}'|'POST /upload'|'POST /images' $route
     */
    #[DataProvider('serverFailures')]
    public function testFailureOnAnyRouteReturns500WithoutLeakingDetails(string $route, Throwable $failure): void
    {
        $logger = $this->spyLogger();
        $this->files->jpeg(60, 40, 'thumb_v1.jpg');
        $kernel = $this->failingKernel($route, $failure, $logger);

        $request = match ($route) {
            'GET /health' => new ServerRequest('GET', '/health'),
            'GET /images' => new ServerRequest('GET', '/images'),
            'GET /files/{name}' => new ServerRequest('GET', '/files/thumb_v1.jpg'),
            'POST /upload' => $this->multipartRequest(
                '/upload',
                ['file' => $this->uploadedFile($this->files->jpeg(150, 150), 'photo.jpg')],
                ['max_width' => '60', 'max_height' => '40'],
            ),
            'POST /images' => $this->multipartRequest(
                '/images',
                ['file' => $this->uploadedFile($this->files->jpeg(150, 150), 'photo.jpg')],
            ),
        };

        $response = $kernel->handle($request);
        $body = (string) $response->getBody();

        $this->assertErrorEnvelope(500, [[null, 'server_error']], $response);
        $this->assertStringNotContainsString('/var/www', $body);
        $this->assertStringNotContainsString('AKIA', $body);
        $this->assertStringNotContainsString('secret', $body);
        $this->assertSame([['error', 'Request failed', ['exception' => $failure]]], $logger->records);
    }

    public function testClientErrorsPreserveStatusAndDoNotLogErrors(): void
    {
        $logger = $this->spyLogger();
        $kernel = Wiring::httpKernel(logger: $logger);

        $badUploadType = $kernel->handle($this->multipartRequest('/upload', contentType: 'application/json'));
        $badImagesType = $kernel->handle($this->multipartRequest('/images', contentType: 'application/json'));
        $invalidUpload = $kernel->handle($this->multipartRequest('/upload'));
        $invalidImages = $kernel->handle($this->multipartRequest('/images'));

        $this->assertErrorEnvelope(400, [[null, 'malformed_request']], $badUploadType);
        $this->assertErrorEnvelope(400, [[null, 'malformed_request']], $badImagesType);
        $this->assertErrorEnvelope(422, [['file', 'required'], ['max_width', 'required'], ['max_height', 'required']], $invalidUpload);
        $this->assertErrorEnvelope(422, [['file', 'required']], $invalidImages);
        $this->assertSame([], $logger->records);
    }

    private function kernelWithDirectories(string $storageDirectory, string $inputDirectory): HttpKernel
    {
        putenv('INPUT_DIR=' . $inputDirectory);
        try {
            $container = Wiring::container();
            $factory = $container->httpFactory();

            return Wiring::httpKernel(
                container: $container,
                fileController: new FileController(
                    $storageDirectory,
                    $factory,
                    $factory,
                    $container->notFoundController(),
                ),
            );
        } finally {
            putenv('INPUT_DIR');
        }
    }

    /**
     * @param 'GET /health'|'GET /images'|'GET /files/{name}'|'POST /upload'|'POST /images' $route
     */
    private function failingKernel(string $route, Throwable $failure, AbstractLogger $logger): HttpKernel
    {
        $container = Wiring::container();
        $factory = $container->httpFactory();
        $throwingResponseFactory = new class ($failure) implements ResponseFactoryInterface {
            public function __construct(private Throwable $failure) {}

            public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
            {
                throw $this->failure;
            }
        };

        $failingLibrary = new class ($failure) implements ImageLibrary {
            public function __construct(private Throwable $failure) {}

            public function names(): array
            {
                throw $this->failure;
            }

            public function path(string $name): ?string
            {
                return null;
            }

            public function addWithinLimit(string $sourcePath, string $name, int $limit): ImageAddOutcome
            {
                throw $this->failure;
            }
        };

        $failingReencoder = new class ($failure) implements ImageReencoder {
            public function __construct(private Throwable $failure) {}

            public function reencode(string $sourcePath, ImageFormat $format): string
            {
                throw $this->failure;
            }
        };

        $failingStorages = new class ($failure) implements StorageRegistry {
            public function __construct(private Throwable $failure) {}

            public function get(StorageType $type): StorageServiceInterface
            {
                throw $this->failure;
            }
        };

        return match ($route) {
            'GET /health' => Wiring::httpKernel(
                container: $container,
                healthController: new HealthController($throwingResponseFactory, $factory),
                logger: $logger,
            ),
            'GET /images', 'POST /images' => Wiring::httpKernel(
                container: $container,
                imagesController: new ImagesController(
                    new InputImages($failingLibrary, $container->imageFileRules(), new NameRule(), $failingReencoder),
                    $container->jsonResponses(),
                    Wiring::config()->postMaxSize,
                ),
                logger: $logger,
            ),
            'GET /files/{name}' => Wiring::httpKernel(
                container: $container,
                fileController: new FileController(
                    $this->files->directory(),
                    $throwingResponseFactory,
                    $factory,
                    $container->notFoundController(),
                ),
                logger: $logger,
            ),
            'POST /upload' => Wiring::httpKernel(
                container: $container,
                imageController: Wiring::imageController(Wiring::resizeAndStoreImage(storages: $failingStorages)),
                logger: $logger,
            ),
        };
    }

    /**
     * @return AbstractLogger&object{records: list<array{mixed, string, array<string, mixed>}>}
     */
    private function spyLogger(): AbstractLogger
    {
        return new class extends AbstractLogger {
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
    }
}

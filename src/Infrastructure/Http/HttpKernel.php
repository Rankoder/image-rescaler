<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Validation\ValidationFailed;
use App\Infrastructure\Controllers\FileController;
use App\Infrastructure\Controllers\HealthController;
use App\Infrastructure\Controllers\ImageController;
use App\Infrastructure\Controllers\ImagesController;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * PSR-7 request dispatcher with method whitelist and the central error boundary.
 */
final readonly class HttpKernel
{
    private const array ALLOWED_METHODS = ['GET', 'POST', 'HEAD'];

    public function __construct(
        private HealthController $healthController,
        private ImageController $imageController,
        private ImagesController $imagesController,
        private FileController $fileController,
        private JsonResponses $responses,
        private StreamFactoryInterface $streamFactory,
        private LoggerInterface $logger,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $method = $request->getMethod();
        $response = $this->respond($request, $method);

        return $method === 'HEAD'
            ? $response->withBody($this->streamFactory->createStream())
            : $response;
    }

    private function respond(ServerRequestInterface $request, string $method): ResponseInterface
    {
        if (!in_array($method, self::ALLOWED_METHODS, true)) {
            return $this->responses->methodNotAllowed();
        }

        if ($method === 'POST' && $this->hasDisallowedMethodOverride($request)) {
            return $this->responses->methodNotAllowed();
        }

        $routeMethod = $method === 'HEAD' ? 'GET' : $method;
        $path = $request->getUri()->getPath();

        try {
            return $this->dispatch($routeMethod, $path, $request);
        } catch (ValidationFailed $failure) {
            return $this->responses->validationFailed($failure->errors);
        } catch (Throwable $failure) {
            // The details name buckets, paths and libraries, so they go to the log, not to the client
            $this->logger->error('Request failed', ['exception' => $failure]);

            return $this->responses->serverError();
        }
    }

    private function dispatch(string $method, string $path, ServerRequestInterface $request): ResponseInterface
    {
        return match ([$method, $path]) {
            ['GET', '/health'] => $this->healthController->check(),
            ['POST', '/upload'] => $this->imageController->upload($request),
            ['GET', '/images'] => $this->imagesController->list(),
            ['POST', '/images'] => $this->imagesController->add($request),
            default => $method === 'GET' && preg_match('#^/files/([^/]+)\z#', $path, $matches) === 1
                ? $this->fileController->show($matches[1])
                : $this->responses->notFound(),
        };
    }

    private function hasDisallowedMethodOverride(ServerRequestInterface $request): bool
    {
        foreach ($request->getHeader('X-HTTP-Method-Override') as $headerValue) {
            foreach (explode(',', $headerValue) as $part) {
                $override = strtoupper(trim($part));
                if ($override !== '' && $override !== 'POST') {
                    return true;
                }
            }
        }

        return false;
    }
}

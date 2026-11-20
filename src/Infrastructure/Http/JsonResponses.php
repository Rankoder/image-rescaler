<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Validation\ValidationError;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * The JSON responses the controllers share, in the shapes openapi.yaml defines.
 */
final readonly class JsonResponses
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {}

    /**
     * @param array<string, mixed> $body
     */
    public function json(int $status, array $body): ResponseInterface
    {
        return $this->responseFactory->createResponse($status)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streamFactory->createStream(json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)));
    }

    /**
     * @param list<ValidationError> $errors
     */
    public function validationFailed(array $errors): ResponseInterface
    {
        return $this->json(422, [
            'status' => 'error',
            'errors' => array_map(
                static fn(ValidationError $error): array => [
                    'field' => $error->field?->value,
                    'code' => $error->code->value,
                    'message' => $error->message,
                ],
                $errors,
            ),
        ]);
    }

    public function malformedRequest(string $message): ResponseInterface
    {
        return $this->json(400, [
            'status' => 'error',
            'errors' => [['field' => null, 'code' => 'malformed_request', 'message' => $message]],
        ]);
    }

    public function serverError(): ResponseInterface
    {
        return $this->json(500, ['status' => 'error', 'message' => 'The image could not be processed or stored.']);
    }
}

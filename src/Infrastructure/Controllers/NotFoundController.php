<?php

declare(strict_types=1);

namespace App\Infrastructure\Controllers;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

final readonly class NotFoundController
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {}

    public function respond(): ResponseInterface
    {
        $body = json_encode(['status' => 'error', 'message' => 'Not found'], JSON_THROW_ON_ERROR);

        return $this->responseFactory->createResponse(404)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streamFactory->createStream($body));
    }
}

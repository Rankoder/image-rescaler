<?php

declare(strict_types=1);

namespace App\Infrastructure\Controllers;

use finfo;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Serves images stored with the local service; only plain names of files directly in the storage directory.
 */
final readonly class FileController
{
    private const string NAME_PATTERN = '/^[A-Za-z0-9_-]{1,200}\.(jpg|png|webp)\z/';
    private const array SERVED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private string $storageDirectory,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private NotFoundController $notFound,
    ) {}

    public function show(string $name): ResponseInterface
    {
        $path = $this->storageDirectory . '/' . $name;
        if (preg_match(self::NAME_PATTERN, $name) !== 1 || !is_file($path)) {
            return $this->notFound->respond();
        }

        $type = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!in_array($type, self::SERVED_TYPES, true)) {
            return $this->notFound->respond();
        }

        return $this->responseFactory->createResponse(200)
            ->withHeader('Content-Type', $type)
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Cache-Control', 'public, max-age=86400')
            ->withBody($this->streamFactory->createStreamFromFile($path));
    }
}

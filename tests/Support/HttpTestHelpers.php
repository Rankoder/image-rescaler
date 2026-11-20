<?php

declare(strict_types=1);

namespace Tests\Support;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UploadedFileInterface;

trait HttpTestHelpers
{
    /**
     * @param array<string, UploadedFileInterface> $files
     * @param array<string, string> $fields
     */
    private function multipartRequest(
        string $path,
        array $files = [],
        array $fields = [],
        string $contentType = 'multipart/form-data; boundary=contract',
    ): ServerRequest {
        return (new ServerRequest('POST', $path, ['Content-Type' => $contentType]))
            ->withUploadedFiles($files)
            ->withParsedBody($fields);
    }

    private function uploadedFile(string $source, string $clientFilename, int $uploadError = UPLOAD_ERR_OK): UploadedFileInterface
    {
        $factory = new Psr17Factory();

        return $factory->createUploadedFile(
            $factory->createStreamFromFile($source),
            (int) filesize($source),
            $uploadError,
            $clientFilename,
        );
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

    /**
     * @param list<array{?string, string}> $expected field and code of every error
     */
    private function assertErrorEnvelope(int $status, array $expected, ResponseInterface $response): void
    {
        $this->assertSame($status, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $payload = $this->json($response);
        $this->assertSame('error', $payload['status'] ?? null);
        $errors = $payload['errors'] ?? null;
        $this->assertIsArray($errors);

        $actual = [];
        foreach ($errors as $error) {
            $this->assertIsArray($error);
            $actual[] = [$error['field'] ?? null, $error['code'] ?? null];
        }
        $this->assertEqualsCanonicalizing($expected, $actual);
    }
}

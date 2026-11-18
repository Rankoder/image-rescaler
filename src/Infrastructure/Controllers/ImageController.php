<?php

declare(strict_types=1);

namespace App\Infrastructure\Controllers;

use App\Application\Services\ResizeAndStoreImage;
use App\Application\Validation\ErrorCode;
use App\Application\Validation\ResizeInput;
use App\Application\Validation\SourceFile;
use App\Application\Validation\ValidationError;
use App\Application\Validation\ValidationFailed;
use Exception;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

final readonly class ImageController
{
    public function __construct(
        private ResizeAndStoreImage $resizeAndStoreImage,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {}

    public function upload(ServerRequestInterface $request): ResponseInterface
    {
        if (!str_starts_with(strtolower($request->getHeaderLine('Content-Type')), 'multipart/form-data')) {
            return $this->malformedRequest('The request must be multipart/form-data.');
        }

        $file = $request->getUploadedFiles()['file'] ?? null;
        if ($file instanceof UploadedFileInterface && $file->getError() === UPLOAD_ERR_PARTIAL) {
            return $this->malformedRequest('The file was only partly uploaded.');
        }

        try {
            $stored = $this->resizeAndStoreImage->handle($this->inputFrom($request, $file));

            return $this->json(200, ['status' => 'success', 'path' => $stored->path, 'url' => $stored->url]);
        } catch (ValidationFailed $failure) {
            return $this->json(422, [
                'status' => 'error',
                'errors' => array_map(
                    static fn(ValidationError $error): array => [
                        'field' => $error->field->value,
                        'code' => $error->code->value,
                        'message' => $error->message,
                    ],
                    $failure->errors,
                ),
            ]);
        } catch (Exception $exception) {
            return $this->json(500, ['status' => 'error', 'message' => $exception->getMessage()]);
        }
    }

    private function inputFrom(ServerRequestInterface $request, mixed $file): ResizeInput
    {
        $fields = $request->getParsedBody();
        $field = static fn(string $name): ?string => is_array($fields) && is_string($fields[$name] ?? null) ? $fields[$name] : null;

        return new ResizeInput(
            $file instanceof UploadedFileInterface ? $this->sourceFile($file) : null,
            $field('max_width'),
            $field('max_height'),
            $field('service'),
            $field('output_filename'),
        );
    }

    private function sourceFile(UploadedFileInterface $file): ?SourceFile
    {
        $name = $file->getClientFilename() ?? '';

        return match ($file->getError()) {
            UPLOAD_ERR_OK => SourceFile::at($this->temporaryPath($file), $name),
            UPLOAD_ERR_NO_FILE => null,
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => SourceFile::rejected(ErrorCode::TooLarge, $name),
            default => throw new RuntimeException('The uploaded file could not be stored on the server.'),
        };
    }

    private function temporaryPath(UploadedFileInterface $file): string
    {
        $path = $file->getStream()->getMetadata('uri');
        if (!is_string($path)) {
            throw new RuntimeException('The uploaded file has no temporary path.');
        }

        return $path;
    }

    private function malformedRequest(string $message): ResponseInterface
    {
        return $this->json(400, [
            'status' => 'error',
            'errors' => [['field' => null, 'code' => 'malformed_request', 'message' => $message]],
        ]);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function json(int $status, array $body): ResponseInterface
    {
        return $this->responseFactory->createResponse($status)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streamFactory->createStream(json_encode($body, JSON_THROW_ON_ERROR)));
    }
}

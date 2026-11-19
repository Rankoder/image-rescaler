<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Validation\ErrorCode;
use App\Application\Validation\Field;
use App\Application\Validation\SourceFile;
use App\Application\Validation\ValidationError;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

/**
 * Reads a multipart/form-data request the way PHP hands it over: its fields, its file, and the problems of either.
 */
final readonly class MultipartRequest
{
    public function __construct(
        private ServerRequestInterface $request,
        private int $postMaxSize,
    ) {}

    /**
     * The response for a request whose form cannot be read at all; null when it can.
     */
    public function unreadable(JsonResponses $responses): ?ResponseInterface
    {
        if (!str_starts_with(strtolower($this->request->getHeaderLine('Content-Type')), 'multipart/form-data')) {
            return $responses->malformedRequest('The request must be multipart/form-data.');
        }
        // PHP drops the whole body of a request over post_max_size and only logs a warning
        if ($this->postMaxSize > 0 && (int) $this->request->getHeaderLine('Content-Length') > $this->postMaxSize) {
            return $responses->validationFailed([
                new ValidationError(Field::File, ErrorCode::TooLarge, 'The request is larger than the server accepts.'),
            ]);
        }
        if ($this->uploadedFile()?->getError() === UPLOAD_ERR_PARTIAL) {
            return $responses->malformedRequest('The file was only partly uploaded.');
        }

        return null;
    }

    public function hasFile(): bool
    {
        $file = $this->uploadedFile();

        return $file !== null && $file->getError() !== UPLOAD_ERR_NO_FILE;
    }

    public function field(string $name): ?string
    {
        $fields = $this->request->getParsedBody();

        return is_array($fields) && is_string($fields[$name] ?? null) ? $fields[$name] : null;
    }

    /**
     * @throws RuntimeException when the server could not keep the uploaded file
     */
    public function file(): ?SourceFile
    {
        $file = $this->uploadedFile();
        if ($file === null) {
            return null;
        }
        $name = $file->getClientFilename() ?? '';

        return match ($file->getError()) {
            UPLOAD_ERR_OK => SourceFile::at($this->temporaryPath($file), $name),
            UPLOAD_ERR_NO_FILE => null,
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => SourceFile::rejected(ErrorCode::TooLarge, $name, Field::File),
            default => throw new RuntimeException('The uploaded file could not be stored on the server.'),
        };
    }

    private function uploadedFile(): ?UploadedFileInterface
    {
        $file = $this->request->getUploadedFiles()['file'] ?? null;

        return $file instanceof UploadedFileInterface ? $file : null;
    }

    private function temporaryPath(UploadedFileInterface $file): string
    {
        $path = $file->getStream()->getMetadata('uri');
        if (!is_string($path)) {
            throw new RuntimeException('The uploaded file has no temporary path.');
        }

        return $path;
    }
}

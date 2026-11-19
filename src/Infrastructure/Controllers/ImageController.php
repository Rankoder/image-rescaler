<?php

declare(strict_types=1);

namespace App\Infrastructure\Controllers;

use App\Application\Services\ResizeAndStoreImage;
use App\Application\Validation\ErrorCode;
use App\Application\Validation\Field;
use App\Application\Validation\ResizeInput;
use App\Application\Validation\SourceFile;
use App\Application\Validation\ValidationError;
use App\Application\Validation\ValidationFailed;
use App\Infrastructure\Filesystem\InputDirectory;
use App\Infrastructure\Http\JsonResponses;
use App\Infrastructure\Http\MultipartRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class ImageController
{
    public function __construct(
        private ResizeAndStoreImage $resizeAndStoreImage,
        private JsonResponses $responses,
        private InputDirectory $inputDirectory,
        private int $postMaxSize,
        private LoggerInterface $logger,
    ) {}

    public function upload(ServerRequestInterface $request): ResponseInterface
    {
        $form = new MultipartRequest($request, $this->postMaxSize);
        $unreadable = $form->unreadable($this->responses);
        if ($unreadable !== null) {
            return $unreadable;
        }

        $image = $form->field('image');
        $image = $image === '' ? null : $image;
        if ($image !== null && $form->hasFile()) {
            return $this->responses->validationFailed([
                new ValidationError(Field::Image, ErrorCode::ConflictsWithFile, 'Give either file or image, not both.'),
            ]);
        }

        try {
            $stored = $this->resizeAndStoreImage->handle(new ResizeInput(
                $image === null ? $form->file() : $this->imageFromInputDirectory($image),
                $form->field('max_width'),
                $form->field('max_height'),
                $form->field('service'),
                $form->field('output_filename'),
            ));

            return $this->responses->json(200, ['status' => 'success', 'path' => $stored->path, 'url' => $stored->url]);
        } catch (ValidationFailed $failure) {
            return $this->responses->validationFailed($failure->errors);
        } catch (Throwable $failure) {
            // The details name buckets, paths and libraries, so they go to the log, not to the client
            $this->logger->error('Upload failed', ['exception' => $failure]);

            return $this->responses->serverError();
        }
    }

    // Over HTTP every name that is not a plain image in the directory is simply not found, ../ and hidden files included
    private function imageFromInputDirectory(string $name): SourceFile
    {
        $source = InputDirectory::isImageName($name) ? $this->inputDirectory->sourceFile($name, Field::Image) : null;

        return $source === null || $source->problem !== null
            ? SourceFile::rejected(ErrorCode::NotFound, $name, Field::Image)
            : $source;
    }
}

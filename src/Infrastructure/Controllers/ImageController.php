<?php

declare(strict_types=1);

namespace App\Infrastructure\Controllers;

use App\Application\Services\ResizeAndStoreImage;
use App\Application\Validation\ResizeInput;
use App\Application\Validation\ValidationFailed;
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

        try {
            $stored = $this->resizeAndStoreImage->handle(new ResizeInput(
                $form->file(),
                $form->field('max_width'),
                $form->field('max_height'),
                $form->field('service'),
                $form->field('output_filename'),
                $form->field('image'),
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
}

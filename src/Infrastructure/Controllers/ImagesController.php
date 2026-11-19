<?php

declare(strict_types=1);

namespace App\Infrastructure\Controllers;

use App\Application\Services\InputImages;
use App\Application\Validation\ValidationFailed;
use App\Infrastructure\Http\JsonResponses;
use App\Infrastructure\Http\MultipartRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * GET /images and POST /images: the input directory over HTTP.
 */
final readonly class ImagesController
{
    public function __construct(
        private InputImages $inputImages,
        private JsonResponses $responses,
        private int $postMaxSize,
        private LoggerInterface $logger,
    ) {}

    public function list(): ResponseInterface
    {
        return $this->responses->json(200, ['images' => $this->inputImages->list()]);
    }

    public function add(ServerRequestInterface $request): ResponseInterface
    {
        $form = new MultipartRequest($request, $this->postMaxSize);
        $unreadable = $form->unreadable($this->responses);
        if ($unreadable !== null) {
            return $unreadable;
        }

        try {
            $name = $this->inputImages->add($form->file(), $form->field('name'));

            return $this->responses->json(201, ['status' => 'success', 'name' => $name]);
        } catch (ValidationFailed $failure) {
            return $this->responses->validationFailed($failure->errors);
        } catch (Throwable $failure) {
            $this->logger->error('Adding an image failed', ['exception' => $failure]);

            return $this->responses->serverError();
        }
    }
}

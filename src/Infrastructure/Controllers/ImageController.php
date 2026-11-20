<?php

declare(strict_types=1);

namespace App\Infrastructure\Controllers;

use App\Application\Services\ResizeAndStoreImage;
use App\Application\Validation\ResizeInput;
use App\Infrastructure\Http\JsonResponses;
use App\Infrastructure\Http\MultipartRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ImageController
{
    public function __construct(
        private ResizeAndStoreImage $resizeAndStoreImage,
        private JsonResponses $responses,
        private int $postMaxSize,
    ) {}

    public function upload(ServerRequestInterface $request): ResponseInterface
    {
        $form = new MultipartRequest($request, $this->postMaxSize);
        $unreadable = $form->unreadable($this->responses);
        if ($unreadable !== null) {
            return $unreadable;
        }

        $stored = $this->resizeAndStoreImage->handle(new ResizeInput(
            $form->file(),
            $form->field('max_width'),
            $form->field('max_height'),
            $form->field('service'),
            $form->field('output_filename'),
            $form->field('image'),
        ));

        return $this->responses->json(200, ['status' => 'success', 'path' => $stored->path, 'url' => $stored->url]);
    }
}

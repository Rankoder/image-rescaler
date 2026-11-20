<?php

declare(strict_types=1);

namespace App\Infrastructure\Controllers;

use App\Infrastructure\Http\JsonResponses;
use Psr\Http\Message\ResponseInterface;

final readonly class NotFoundController
{
    public function __construct(private JsonResponses $responses) {}

    public function respond(): ResponseInterface
    {
        return $this->responses->notFound();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Controllers;

use App\Infrastructure\Controllers\NotFoundController;
use App\Infrastructure\Http\JsonResponses;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class NotFoundControllerTest extends TestCase
{
    public function testRespondsWithAJsonError(): void
    {
        $psr17Factory = new Psr17Factory();

        $response = (new NotFoundController(new JsonResponses($psr17Factory, $psr17Factory)))->respond();

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(
            '{"status":"error","errors":[{"field":null,"code":"not_found","message":"Not found."}]}',
            (string) $response->getBody(),
        );
    }
}

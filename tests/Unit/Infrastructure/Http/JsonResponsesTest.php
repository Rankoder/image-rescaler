<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http;

use App\Infrastructure\Http\JsonResponses;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class JsonResponsesTest extends TestCase
{
    public function testJsonDoesNotEscapeSlashesInUrls(): void
    {
        $factory = new Psr17Factory();
        $responses = new JsonResponses($factory, $factory);

        $response = $responses->json(200, ['url' => 'https://example.com/files/photo_v1.jpg']);

        $body = (string) $response->getBody();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('{"url":"https://example.com/files/photo_v1.jpg"}', $body);
        $this->assertStringNotContainsString('\/', $body);
    }

    public function testNotFoundUsesTheErrorEnvelope(): void
    {
        $factory = new Psr17Factory();
        $responses = new JsonResponses($factory, $factory);

        $response = $responses->notFound();

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(
            '{"status":"error","errors":[{"field":null,"code":"not_found","message":"Not found."}]}',
            (string) $response->getBody(),
        );
    }

    public function testServerErrorUsesTheErrorEnvelope(): void
    {
        $factory = new Psr17Factory();
        $responses = new JsonResponses($factory, $factory);

        $response = $responses->serverError();

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(
            '{"status":"error","errors":[{"field":null,"code":"server_error","message":"The image could not be processed or stored."}]}',
            (string) $response->getBody(),
        );
    }
}

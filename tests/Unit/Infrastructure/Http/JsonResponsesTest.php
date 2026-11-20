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
}

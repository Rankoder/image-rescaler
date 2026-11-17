<?php

declare(strict_types=1);

namespace App\Infrastructure\Controllers;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class HealthControllerTest extends TestCase
{
    public function testCheckReportsOkAsUncachedJson(): void
    {
        $psr17Factory = new Psr17Factory();

        $response = (new HealthController($psr17Factory, $psr17Factory))->check();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        $this->assertSame('{"status":"ok"}', (string) $response->getBody());
    }
}

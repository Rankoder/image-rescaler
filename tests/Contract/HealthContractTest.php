<?php

declare(strict_types=1);

namespace Tests\Contract;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Tests\Support\Wiring;

final class HealthContractTest extends TestCase
{
    use MatchesContract;

    public function testHealthAnswersAsTheContractSays(): void
    {
        $response = Wiring::httpKernel()->handle(new ServerRequest('GET', '/health'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        $this->assertMatchesContract('get', '/health', $response);
    }
}

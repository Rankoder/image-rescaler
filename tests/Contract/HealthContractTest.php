<?php

declare(strict_types=1);

namespace Tests\Contract;

use App\Infrastructure\Controllers\HealthController;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class HealthContractTest extends TestCase
{
    use MatchesContract;

    public function testHealthAnswersAsTheContractSays(): void
    {
        $factory = new Psr17Factory();

        $response = (new HealthController($factory, $factory))->check();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        $this->assertMatchesContract('get', '/health', $response);
    }
}

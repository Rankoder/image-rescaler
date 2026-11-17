<?php

declare(strict_types=1);

namespace Tests\Functional;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class RoutesTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testAnUnknownPathGetsAJsonNotFound(): void
    {
        $this->assertSame('{"status":"error","message":"Not found"}', $this->dispatch('GET', '/nope'));
    }

    private function dispatch(string $method, string $uri): string
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        ob_start();
        require __DIR__ . '/../../routes.php';

        return (string) ob_get_clean();
    }
}

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

    #[RunInSeparateProcess]
    public function testAMethodWithNoRoutesGetsAJsonNotFoundWithoutWarnings(): void
    {
        set_error_handler(static function (int $level, string $message): never {
            throw new \ErrorException($message, 0, $level);
        });

        try {
            $this->assertSame('{"status":"error","message":"Not found"}', $this->dispatch('PUT', '/upload'));
        } finally {
            restore_error_handler();
        }
    }

    private function dispatch(string $method, string $uri): string
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        ob_start();
        try {
            require __DIR__ . '/../../routes.php';
        } finally {
            $output = (string) ob_get_clean();
        }

        return $output;
    }
}

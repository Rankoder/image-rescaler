<?php

declare(strict_types=1);

namespace Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class RoutesTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testAnUnknownPathGetsAJsonNotFound(): void
    {
        $this->assertSame(
            '{"status":"error","errors":[{"field":null,"code":"not_found","message":"Not found."}]}',
            $this->dispatch('GET', '/nope'),
        );
        $this->assertSame(404, http_response_code());
    }

    #[RunInSeparateProcess]
    public function testAnAllowedMethodOnAPathOfAnotherMethodGetsAJsonNotFoundWithoutWarnings(): void
    {
        set_error_handler(static function (int $level, string $message): never {
            throw new \ErrorException($message, 0, $level);
        });

        try {
            $this->assertSame(
                '{"status":"error","errors":[{"field":null,"code":"not_found","message":"Not found."}]}',
                $this->dispatch('GET', '/upload'),
            );
            $this->assertSame(404, http_response_code());
        } finally {
            restore_error_handler();
        }
    }

    #[RunInSeparateProcess]
    public function testHeadRequestAnswersWithoutABodyOrWarnings(): void
    {
        set_error_handler(static function (int $level, string $message): never {
            throw new \ErrorException($message, 0, $level);
        });

        try {
            $this->assertSame('', $this->dispatch('HEAD', '/health'));
            $this->assertSame(200, http_response_code());
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unsupportedMethods(): iterable
    {
        yield 'PUT' => ['PUT', '/upload'];
        yield 'DELETE' => ['DELETE', '/images'];
        yield 'PATCH' => ['PATCH', '/upload'];
        yield 'OPTIONS' => ['OPTIONS', '/health'];
        yield 'PROPFIND' => ['PROPFIND', '/upload'];
        yield 'TRACE' => ['TRACE', '/health'];
    }

    #[DataProvider('unsupportedMethods')]
    #[RunInSeparateProcess]
    public function testAnUnsupportedMethodGetsMethodNotAllowedWithoutWarnings(string $method, string $uri): void
    {
        set_error_handler(static function (int $level, string $message): never {
            throw new \ErrorException($message, 0, $level);
        });

        try {
            $this->assertSame(
                '{"status":"error","errors":[{"field":null,"code":"method_not_allowed","message":"Method not allowed."}]}',
                $this->dispatch($method, $uri),
            );
            $this->assertSame(405, http_response_code());
        } finally {
            restore_error_handler();
        }
    }

    #[RunInSeparateProcess]
    public function testAPostWithAnUnsupportedMethodOverrideGetsMethodNotAllowedWithoutWarnings(): void
    {
        set_error_handler(static function (int $level, string $message): never {
            throw new \ErrorException($message, 0, $level);
        });

        $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] = 'PUT';
        try {
            $this->assertSame(
                '{"status":"error","errors":[{"field":null,"code":"method_not_allowed","message":"Method not allowed."}]}',
                $this->dispatch('POST', '/upload'),
            );
            $this->assertSame(405, http_response_code());
        } finally {
            unset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
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

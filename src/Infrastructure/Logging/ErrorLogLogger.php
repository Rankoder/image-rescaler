<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use Psr\Log\AbstractLogger;
use Stringable;
use Throwable;

/**
 * Writes to PHP's error log, which the container sends to its own log (docker compose logs web).
 */
final class ErrorLogLogger extends AbstractLogger
{
    /**
     * @param array<string, mixed> $context
     */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $line = sprintf('[%s] %s', is_string($level) ? $level : 'log', $message);

        $exception = $context['exception'] ?? null;
        if ($exception instanceof Throwable) {
            $line .= sprintf(
                ': %s: %s in %s:%d%s%s',
                $exception::class,
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
                PHP_EOL,
                $exception->getTraceAsString(),
            );
        }

        error_log($line);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Application\Validation\ErrorCode;
use App\Application\Validation\Field;
use App\Application\Validation\ValidationError;
use App\Application\Validation\ValidationFailed;

trait AssertsValidationFailure
{
    /**
     * @param list<array{?Field, ErrorCode}> $expected
     * @param callable(): mixed $action
     */
    private function assertValidationFails(array $expected, callable $action): void
    {
        try {
            $action();
            $this->fail('Expected ValidationFailed to be thrown.');
        } catch (ValidationFailed $failure) {
            $this->assertSame(
                $expected,
                array_map(static fn(ValidationError $error): array => [$error->field, $error->code], $failure->errors),
            );
        }
    }
}

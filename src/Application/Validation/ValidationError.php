<?php

declare(strict_types=1);

namespace App\Application\Validation;

final readonly class ValidationError
{
    public function __construct(
        public Field $field,
        public ErrorCode $code,
        public string $message,
    ) {}
}

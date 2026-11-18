<?php

declare(strict_types=1);

namespace App\Application\Validation;

use DomainException;

final class ValidationFailed extends DomainException
{
    /**
     * @param list<ValidationError> $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('The resize request is invalid.');
    }
}

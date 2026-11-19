<?php

declare(strict_types=1);

namespace App\Domain\Enums;

enum ImageAddOutcome
{
    case Added;
    case AlreadyExists;
    case LimitReached;
}

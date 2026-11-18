<?php

declare(strict_types=1);

namespace App\Application\Validation;

enum Field: string
{
    case File = 'file';
    case MaxWidth = 'max_width';
    case MaxHeight = 'max_height';
    case Service = 'service';
    case OutputFilename = 'output_filename';
}

<?php

declare(strict_types=1);

namespace App\Domain\Enums;

enum StorageType: string
{
    case Local = 'local';
    case S3 = 's3';
    case Dropbox = 'dropbox';
}

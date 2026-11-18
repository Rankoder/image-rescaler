<?php

declare(strict_types=1);

namespace App\Domain\Interfaces;

use App\Domain\Enums\StorageType;

interface StorageRegistry
{
    public function get(StorageType $type): StorageServiceInterface;
}

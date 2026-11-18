<?php

declare(strict_types=1);

namespace App\Application\Services;

final readonly class StoredImage
{
    public function __construct(
        public string $path,
        public string $url,
    ) {}
}

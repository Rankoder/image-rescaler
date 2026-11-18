<?php

declare(strict_types=1);

namespace App\Infrastructure\Config;

final readonly class AwsConfig
{
    public function __construct(
        public string $accessKeyId,
        public string $secretAccessKey,
        public string $region,
        public string $bucket,
    ) {}
}

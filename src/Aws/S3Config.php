<?php

declare(strict_types=1);

namespace App\Aws;

class S3Config
{
    public function __construct(
        public readonly string $bucket,
        public readonly string $version,
        public readonly string $region
    ) {
    }
}

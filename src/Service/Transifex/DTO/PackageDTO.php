<?php

declare(strict_types=1);

namespace App\Service\Transifex\DTO;

class PackageDTO
{
    public function __construct(
        public readonly string $translationsDir,
        public readonly array $filterLanguages,
        public readonly string $packagesTimestampDir
    ) {
    }
}

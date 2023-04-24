<?php

declare(strict_types=1);

namespace App\Service\Transifex\DTO;

class PackageDTO
{
    /**
     * @param array<string> $filterLanguages
     */
    public function __construct(
        public readonly string $translationsDir,
        public readonly array $filterLanguages,
        public readonly string $packagesTimestampDir
    ) {
    }
}

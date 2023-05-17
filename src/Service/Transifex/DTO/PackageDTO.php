<?php

declare(strict_types=1);

namespace App\Service\Transifex\DTO;

class PackageDTO
{
    /**
     * @param array<string> $skipLanguages
     */
    public function __construct(
        public readonly string $translationsDir,
        public readonly array $skipLanguages,
        public readonly string $packagesTimestampDir
    ) {
    }
}

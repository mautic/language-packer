<?php

declare(strict_types=1);

namespace App\Service\Transifex\DTO;

class TranslationDTO
{
    public function __construct(
        public readonly string $translationsDir,
        public readonly string $slug,
        public readonly string $language,
        public readonly string $bundle,
        public readonly string $file,
        public readonly string $lastUpdate,
    ) {
    }
}

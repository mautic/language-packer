<?php

declare(strict_types=1);

namespace App\Service\Transifex\DTO;

class ResourceDTO
{
    public function __construct(
        public readonly string $translationsDir,
        public readonly array $filterLanguages,
        public readonly string $language,
        public string $resourceSlug = '',
        public string $resourceName = ''
    ) {
    }
}

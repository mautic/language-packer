<?php

declare(strict_types=1);

namespace App\Service\Transifex\DTO;

class ResourceDTO
{
    /**
     * @param array<string> $skipLanguages
     * @param array<string> $languages
     */
    public function __construct(
        public readonly string $translationsDir,
        public readonly array $skipLanguages,
        public readonly array $languages,
        public readonly bool $byPassCompletion,
        public string $resourceSlug = '',
        public string $resourceName = '',
    ) {
    }
}

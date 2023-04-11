<?php

declare(strict_types=1);

namespace MauticLanguagePacker\Event;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\EventDispatcher\Event;

class LanguageStatsEvent extends Event
{
    public const NAME = 'mlp.language.stats';

    public function __construct(
        private readonly SymfonyStyle $io,
        private readonly array $resourceAttributes,
        private readonly array $filterLanguages,
        private readonly string $translationsDir,
        private readonly string $language
    ) {
    }

    public function getIo(): SymfonyStyle
    {
        return $this->io;
    }

    public function getResourceAttributes(): array
    {
        return $this->resourceAttributes;
    }

    public function getFilterLanguages(): array
    {
        return $this->filterLanguages;
    }

    public function getTranslationsDir(): string
    {
        return $this->translationsDir;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }
}

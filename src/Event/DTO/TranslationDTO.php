<?php

declare(strict_types=1);

namespace MauticLanguagePacker\Event\DTO;

class TranslationDTO
{
    public function __construct(
        private readonly string $slug,
        private readonly string $language,
        private readonly string $translationsDir,
        private readonly string $bundle,
        private readonly string $file,
        private readonly string $lastUpdate
    ) {
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getTranslationsDir(): string
    {
        return $this->translationsDir;
    }

    public function getBundle(): string
    {
        return $this->bundle;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function getLastUpdate(): string
    {
        return $this->lastUpdate;
    }
}

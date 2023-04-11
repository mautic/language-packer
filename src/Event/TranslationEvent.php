<?php

declare(strict_types=1);

namespace MauticLanguagePacker\Event;

use MauticLanguagePacker\Event\DTO\TranslationDTO;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\EventDispatcher\Event;

class TranslationEvent extends Event
{
    public const NAME = 'mlp.translation';

    public function __construct(
        private readonly SymfonyStyle $io,
        private readonly TranslationDTO $translationDTO
    ) {
    }

    public function getIo(): SymfonyStyle
    {
        return $this->io;
    }

    public function getTranslationDTO(): TranslationDTO
    {
        return $this->translationDTO;
    }
}

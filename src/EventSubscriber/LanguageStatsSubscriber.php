<?php

declare(strict_types=1);

namespace MauticLanguagePacker\EventSubscriber;

use Mautic\Transifex\Connector\Statistics;
use Mautic\Transifex\TransifexInterface;
use MauticLanguagePacker\Event\DTO\TranslationDTO;
use MauticLanguagePacker\Event\LanguageStatsEvent;
use MauticLanguagePacker\Event\TranslationEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class LanguageStatsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TransifexInterface $transifex,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [LanguageStatsEvent::NAME => 'getStatistics'];
    }

    public function getStatistics(LanguageStatsEvent $event): void
    {
        $io                 = $event->getIo();
        $resourceAttributes = $event->getResourceAttributes();
        $filterLanguages    = $event->getFilterLanguages();
        $translationsDir    = $event->getTranslationsDir();

        $slug = $resourceAttributes['slug'] ?? '';
        $name = $resourceAttributes['name'] ?? '';

        // Split the name to create our file name
        [$bundle, $file] = explode(' ', $name);

        $languageStats = $this->transifex->getConnector(Statistics::class);
        $response      = $languageStats->getLanguageStats($slug);
        $statistics    = json_decode($response->getBody()->__toString(), true, 512, JSON_THROW_ON_ERROR);
        $languageStats = $statistics['data'] ?? [];

        foreach ($languageStats as $languageStat) {
            $id         = $languageStat['id'];
            $idParts    = explode(':', $id);
            $language   = end($idParts);
            $attributes = $languageStat['attributes'];
            $lastUpdate = $attributes['last_update'];

            // Skip filtered languages
            if (in_array($language, $filterLanguages, true)) {
                continue;
            }

            $io->writeln(
                '<comment>'.sprintf(
                    'Processing the %1$s "%2$s" resource in "%3$s" language.',
                    $bundle,
                    $file,
                    $language
                ).'</comment>'
            );

            $translationDTO   = new TranslationDTO($slug, $language, $translationsDir, $bundle, $file, $lastUpdate);
            $translationEvent = new TranslationEvent($io, $translationDTO);
            $this->eventDispatcher->dispatch($translationEvent, TranslationEvent::NAME);
        }
    }
}

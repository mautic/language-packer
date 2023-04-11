<?php

declare(strict_types=1);

namespace MauticLanguagePacker\EventSubscriber;

use Mautic\Transifex\Connector\Resources;
use Mautic\Transifex\TransifexInterface;
use MauticLanguagePacker\Event\LanguageStatsEvent;
use MauticLanguagePacker\Event\ResourceEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ResourceSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TransifexInterface $transifex,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [ResourceEvent::NAME => 'getResources'];
    }

    public function getResources(ResourceEvent $event): void
    {
        $io              = $event->getIo();
        $filterLanguages = $event->getFilterLanguages();
        $translationsDir = $event->getTranslationsDir();
        $language        = $event->getLanguage();

        $resources    = $this->transifex->getConnector(Resources::class);
        $response     = $resources->getAll();
        $body         = json_decode($response->getBody()->__toString(), true, 512, JSON_THROW_ON_ERROR);
        $resourceData = $body['data'] ?? [];

        foreach ($resourceData as $resource) {
            $resourceAttributes = $resource['attributes'] ?? [];
            $slug               = $resourceAttributes['slug'] ?? '';

            if ($slug) {
                $languageStatsEvent = new LanguageStatsEvent(
                    $io,
                    $resourceAttributes,
                    $filterLanguages,
                    $translationsDir,
                    $language
                );
                $this->eventDispatcher->dispatch($languageStatsEvent, LanguageStatsEvent::NAME);
            }
        }
    }
}

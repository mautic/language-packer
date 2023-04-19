<?php

declare(strict_types=1);

namespace MauticLanguagePacker\Tests\Functional\EventSubscriber;

use GuzzleHttp\Psr7\Response;
use MauticLanguagePacker\Event\DTO\TranslationDTO;
use MauticLanguagePacker\Event\LanguageStatsEvent;
use MauticLanguagePacker\Event\TranslationEvent;
use MauticLanguagePacker\EventSubscriber\LanguageStatsSubscriber;
use MauticLanguagePacker\Tests\Common\Client\TransifexTestClient;
use MauticLanguagePacker\Tests\Common\Client\TransifexTrait;
use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class LanguageStatsSubscriberTest extends KernelTestCase
{
    use TransifexTrait;

    public function testGetSubscribedEvents(): void
    {
        Assert::assertSame(
            [LanguageStatsEvent::NAME => 'getStatistics'],
            LanguageStatsSubscriber::getSubscribedEvents()
        );
    }

    public function testGetStatistics(): void
    {
        $client     = new TransifexTestClient();
        $lastUpdate = '2017-10-16T20:43:28Z';

        $body = <<<EOT
{
  "data": [
    {
      "id": "o:some-organisation:p:some-project:r:addonbundle-flashes:l:af",
      "attributes": {
        "last_update": "$lastUpdate"
      }
    }
  ]
}
EOT;
        $client->setResponse(new Response(200, [], $body));

        $transifex = $this->getTransifex($client);

        $languageStatsEventMock = $this->createMock(LanguageStatsEvent::class);

        $symfonyStyleMock = $this->createMock(SymfonyStyle::class);
        $languageStatsEventMock->expects(self::once())->method('getIo')->willReturn($symfonyStyleMock);

        $slug = 'addonbundle-flashes';

        $resourceAttributes = ['slug' => $slug, 'name' => 'Addonbundle flashes'];
        $languageStatsEventMock->expects(self::once())->method('getResourceAttributes')->willReturn($resourceAttributes);

        $languageStatsEventMock->expects(self::once())->method('getFilterLanguages')->willReturn(['es', 'en']);

        $translationsDir = self::getContainer()->getParameter('mlp.translations.dir');
        $languageStatsEventMock->expects(self::once())->method('getTranslationsDir')->willReturn($translationsDir);

        $languageStatsEventMock->expects(self::once())->method('getLanguage')->willReturn('es');

        $translationDTO   = new TranslationDTO($slug, 'af', $translationsDir, 'Addonbundle', 'flashes', $lastUpdate);
        $translationEvent = new TranslationEvent($symfonyStyleMock, $translationDTO);

        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcherMock->expects(self::once())->method('dispatch')->with(
            $translationEvent,
            TranslationEvent::NAME
        );

        $languageStatsSubscriber = new LanguageStatsSubscriber($transifex, $eventDispatcherMock);
        $languageStatsSubscriber->getStatistics($languageStatsEventMock);
    }
}

<?php

declare(strict_types=1);

namespace MauticLanguagePacker\Tests\EventSubscriber;

use GuzzleHttp\Psr7\Response;
use Http\Factory\Guzzle\RequestFactory;
use Http\Factory\Guzzle\StreamFactory;
use Http\Factory\Guzzle\UriFactory;
use Mautic\Transifex\Config;
use Mautic\Transifex\Transifex;
use MauticLanguagePacker\Event\DTO\TranslationDTO;
use MauticLanguagePacker\Event\LanguageStatsEvent;
use MauticLanguagePacker\Event\TranslationEvent;
use MauticLanguagePacker\EventSubscriber\LanguageStatsSubscriber;
use MauticLanguagePacker\Tests\Common\Client\TransifexTestClient;
use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class LanguageStatsSubscriberTest extends KernelTestCase
{
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

        $requestFactory = new RequestFactory();
        $streamFactory  = new StreamFactory();
        $uriFactory     = new UriFactory();
        $config         = new Config();

        $config->setApiToken('some-api-token');
        $config->setOrganization('some-organization');
        $config->setProject('some-project');

        $transifex = new Transifex($client, $requestFactory, $streamFactory, $uriFactory, $config);

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

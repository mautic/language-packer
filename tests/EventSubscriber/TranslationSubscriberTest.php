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
use MauticLanguagePacker\Event\PrepareDirEvent;
use MauticLanguagePacker\Event\TranslationEvent;
use MauticLanguagePacker\EventSubscriber\TranslationSubscriber;
use MauticLanguagePacker\Tests\Common\Client\TransifexTestClient;
use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;

class TranslationSubscriberTest extends KernelTestCase
{
    public function testGetSubscribedEvents(): void
    {
        Assert::assertSame(
            [TranslationEvent::NAME => 'getTranslations'],
            TranslationSubscriber::getSubscribedEvents()
        );
    }

    public function testGetTranslations(): void
    {
        $client = new TransifexTestClient();

        $body = <<<EOT
{
  "data": {
    "id": "7eab5699-6168-4d3f-87e4-414093e46bcf",
    "links": {
      "self": "https://rest.api.transifex.com/resource_translations_async_downloads/7eab5699-6168-4d3f-87e4-414093e46bcf"
    }
  }
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

        $translationEventMock = $this->createMock(TranslationEvent::class);
        $slug                 = 'addonbundle-flashes';
        $languageCode         = 'af';
        $bundle               = 'AddonBundle';
        $file                 = 'flashes';
        $lastUpdate           = '2017-10-16T20:43:28Z';

        $translationsDir = self::getContainer()->getParameter('mlp.translations.dir');
        $bundlePath      = $translationsDir.'/'.$languageCode.'/'.$bundle;

        $symfonyStyleMock = $this->createMock(SymfonyStyle::class);
        $translationEventMock->expects(self::once())->method('getIo')->willReturn($symfonyStyleMock);

        $translationDTO = new TranslationDTO($slug, $languageCode, $translationsDir, $bundle, $file, $lastUpdate);
        $translationEventMock->expects(self::once())->method('getTranslationDTO')->willReturn($translationDTO);

        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcherMock->expects(self::once())->method('dispatch')->with(
            new PrepareDirEvent($bundlePath),
            PrepareDirEvent::NAME
        );

        $filesystemMock        = $this->createMock(Filesystem::class);
        $translationSubscriber = new TranslationSubscriber($transifex, $eventDispatcherMock, $filesystemMock);
        $translationSubscriber->getTranslations($translationEventMock);
    }
}

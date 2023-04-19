<?php

declare(strict_types=1);

namespace MauticLanguagePacker\Tests\Functional\EventSubscriber;

use GuzzleHttp\Psr7\Response;
use MauticLanguagePacker\Event\DTO\TranslationDTO;
use MauticLanguagePacker\Event\PrepareDirEvent;
use MauticLanguagePacker\Event\TranslationEvent;
use MauticLanguagePacker\EventSubscriber\TranslationSubscriber;
use MauticLanguagePacker\Tests\Common\Client\TransifexTestClient;
use MauticLanguagePacker\Tests\Common\Client\TransifexTrait;
use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;

class TranslationSubscriberTest extends KernelTestCase
{
    use TransifexTrait;

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

        $transifex = $this->getTransifex($client);

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

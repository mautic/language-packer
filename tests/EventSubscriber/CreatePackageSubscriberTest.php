<?php

declare(strict_types=1);

namespace MauticLanguagePacker\Tests\EventSubscriber;

use GuzzleHttp\Psr7\Response;
use Http\Factory\Guzzle\RequestFactory;
use Http\Factory\Guzzle\StreamFactory;
use Http\Factory\Guzzle\UriFactory;
use Mautic\Transifex\Config;
use Mautic\Transifex\Transifex;
use MauticLanguagePacker\Event\CreatePackageEvent;
use MauticLanguagePacker\EventSubscriber\CreatePackageSubscriber;
use MauticLanguagePacker\Tests\Common\Client\TransifexTestClient;
use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class CreatePackageSubscriberTest extends KernelTestCase
{
    public function testGetSubscribedEvents(): void
    {
        Assert::assertSame(
            [CreatePackageEvent::NAME => 'createPackage'],
            CreatePackageSubscriber::getSubscribedEvents()
        );
    }

    public function testCreatePackage(): void
    {
        $client = new TransifexTestClient();

        $language = 'af';

        $body = <<<EOT
{
  "data": {
    "attributes": {
      "code": "$language",
      "name": "Afrikaans"
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

        $createPackageEventMock = $this->createMock(CreatePackageEvent::class);

        $symfonyStyleMock = $this->createMock(SymfonyStyle::class);
        $createPackageEventMock->expects(self::once())->method('getIo')->willReturn($symfonyStyleMock);

        $createPackageEventMock->expects(self::once())->method('getFilterLanguages')->willReturn(['es', 'en']);

        $translationsDir = self::getContainer()->getParameter('mlp.translations.dir');
        $createPackageEventMock->expects(self::once())->method('getTranslationsDir')->willReturn($translationsDir);

        $packagesTimestampDir = self::getContainer()->getParameter('mlp.packages.dir').'/'.(new \DateTime())->format('YmdHis');
        $createPackageEventMock->expects(self::once())->method('getPackagesTimestampDir')->willReturn($packagesTimestampDir);

        $filesystemMock          = $this->createMock(Filesystem::class);
        $createPackageSubscriber = new CreatePackageSubscriber($transifex, $filesystemMock);
        $createPackageSubscriber->createPackage($createPackageEventMock);

        unlink($translationsDir.'/'.$language.'.zip');
    }
}

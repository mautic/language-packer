<?php

declare(strict_types=1);

namespace App\Tests\Functional\EventSubscriber;

use App\Event\CreatePackageEvent;
use App\EventSubscriber\CreatePackageSubscriber;
use App\Tests\Common\Client\TransifexTestClient;
use App\Tests\Common\Client\TransifexTrait;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class CreatePackageSubscriberTest extends KernelTestCase
{
    use TransifexTrait;

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

        $transifex = $this->getTransifex($client);

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

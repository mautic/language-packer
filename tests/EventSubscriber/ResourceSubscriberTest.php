<?php

declare(strict_types=1);

namespace MauticLanguagePacker\Tests\EventSubscriber;

use GuzzleHttp\Psr7\Response;
use Http\Factory\Guzzle\RequestFactory;
use Http\Factory\Guzzle\StreamFactory;
use Http\Factory\Guzzle\UriFactory;
use Mautic\Transifex\Config;
use Mautic\Transifex\Transifex;
use MauticLanguagePacker\Event\LanguageStatsEvent;
use MauticLanguagePacker\Event\ResourceEvent;
use MauticLanguagePacker\EventSubscriber\ResourceSubscriber;
use MauticLanguagePacker\Tests\Common\Client\TransifexTestClient;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ResourceSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        Assert::assertSame(
            [ResourceEvent::NAME => 'getResources'],
            ResourceSubscriber::getSubscribedEvents()
        );
    }

    public function testGetResources(): void
    {
        $client = new TransifexTestClient();

        $body = <<<EOT
{
  "data": [
    {
      "attributes": {
        "slug": "addonbundle-flashes"
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

        $transifex           = new Transifex($client, $requestFactory, $streamFactory, $uriFactory, $config);
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $resourceEventMock   = $this->createMock(ResourceEvent::class);

        $io = $this->createMock(SymfonyStyle::class);
        $resourceEventMock->method('getIo')->willReturn($io);

        $eventDispatcherMock->expects(self::once())->method('dispatch')->with(
            new LanguageStatsEvent($io, ['slug' => 'addonbundle-flashes'], [], '', ''),
            LanguageStatsEvent::NAME
        );

        $resourceSubscriber = new ResourceSubscriber($transifex, $eventDispatcherMock);
        $resourceSubscriber->getResources($resourceEventMock);
    }
}

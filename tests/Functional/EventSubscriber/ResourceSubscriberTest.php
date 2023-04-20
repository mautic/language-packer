<?php

declare(strict_types=1);

namespace App\Tests\Functional\EventSubscriber;

use App\Event\LanguageStatsEvent;
use App\Event\ResourceEvent;
use App\EventSubscriber\ResourceSubscriber;
use App\Tests\Common\Client\TransifexTestClient;
use App\Tests\Common\Client\TransifexTrait;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ResourceSubscriberTest extends TestCase
{
    use TransifexTrait;

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

        $transifex = $this->getTransifex($client);

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

<?php

declare(strict_types=1);

namespace App\Tests\Transifex\Connector;

use App\Service\Transifex\Connector\Languages;
use App\Tests\Common\Client\MockResponse;
use App\Tests\Common\Trait\ResponseBodyBuilderTrait;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use Http\Factory\Guzzle\RequestFactory;
use Http\Factory\Guzzle\StreamFactory;
use Http\Factory\Guzzle\UriFactory;
use Mautic\Transifex\Config;
use Mautic\Transifex\Transifex;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class LanguagesTest extends TestCase
{
    use ResponseBodyBuilderTrait;

    public function testGetLanguageDetailsSuccessfulResponse(): void
    {
        $language     = 'af';
        $expectedBody = self::buildLanguagesBody($language);
        $mockHandler  = new MockHandler();
        $mockHandler->append(
            MockResponse::fromString($expectedBody)
                ->assertRequestMethod(Request::METHOD_GET)
                ->assertRequestUri("https://rest.api.transifex.com/languages/l%3A$language")
                ->assertRequestHeaders(
                    [
                        'User-Agent'    => ['GuzzleHttp/7'],
                        'Host'          => ['rest.api.transifex.com'],
                        'accept'        => ['application/vnd.api+json'],
                        'content-type'  => ['application/vnd.api+json'],
                        'authorization' => ['Bearer some-api-token'],
                    ]
                )
        );
        $handlerStack = HandlerStack::create($mockHandler);

        $client         = new Client(['handler' => $handlerStack]);
        $requestFactory = new RequestFactory();
        $streamFactory  = new StreamFactory();
        $uriFactory     = new UriFactory();

        $config = new Config();
        $config->setApiToken('some-api-token');
        $config->setOrganization('some-organization');
        $config->setProject('some-project');

        $transifex = new Transifex($client, $requestFactory, $streamFactory, $uriFactory, $config);
        $response  = $transifex->getConnector(Languages::class)->getLanguageDetails($language);
        Assert::assertSame($response->getBody()->getContents(), $expectedBody);
    }
}

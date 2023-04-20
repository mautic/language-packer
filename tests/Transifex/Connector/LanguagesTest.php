<?php

declare(strict_types=1);

namespace App\Tests\Transifex\Connector;

use App\Service\Transifex\Connector\Languages;
use App\Tests\Common\Client\TransifexTestClient;
use GuzzleHttp\Psr7\Response;
use Http\Factory\Guzzle\RequestFactory;
use Http\Factory\Guzzle\StreamFactory;
use Http\Factory\Guzzle\UriFactory;
use Mautic\Transifex\Config;
use Mautic\Transifex\Transifex;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

final class LanguagesTest extends TestCase
{
    private Transifex $transifex;

    private string $sampleString = '{"a":1,"b":2,"c":3,"d":4,"e":5}';

    protected function setUp(): void
    {
        $this->client         = new TransifexTestClient();
        $this->requestFactory = new RequestFactory();
        $this->streamFactory  = new StreamFactory();
        $this->uriFactory     = new UriFactory();
        $this->config         = new Config();

        $this->config->setApiToken('some-api-token');
        $this->config->setOrganization('some-organization');
        $this->config->setProject('some-project');

        $this->transifex = new Transifex(
            $this->client,
            $this->requestFactory,
            $this->streamFactory,
            $this->uriFactory,
            $this->config
        );
    }

    public function testGetLanguageDetailsSuccessfulResponse(): void
    {
        $language     = 'en_US';
        $expectedBody = <<<EOT
{
  "data": {
    "id": "l:en_US",
    "type": "languages",
    "attributes": {
      "code": "en_US",
      "name": "English (United States)",
      "rtl": false,
      "plural_equation": "(n != 1)",
      "plural_rules": {
        "one": "n is 1",
        "other": "everything else"
      }
    },
    "links": {
      "self": "https://rest.api.transifex.com/languages/l:en_US"
    }
  }
}
EOT;
        $this->prepareSuccessTest(200, [], $expectedBody);
        $this->transifex->getConnector(Languages::class)->getLanguageDetails($language);
        $this->assertCorrectRequestAndResponse('/languages/'.urlencode("l:{$language}"));
    }

    private function prepareSuccessTest(
        int $status = 200,
        array $headers = [],
        $body = null
    ): void {
        $this->client->setResponse(new Response($status, $headers, $body ?? $this->sampleString));
    }

    private function assertCorrectRequestAndResponse(
        string $path,
        string $method = 'GET',
        int $code = 200,
        string $body = ''
    ): void {
        self::assertCorrectRequestMethod($method, $this->client->getRequest()->getMethod());
        self::assertCorrectRequestPath($path, $this->client->getRequest()->getUri()->getPath());
        self::assertCorrectResponseCode($code, $this->client->getResponse()->getStatusCode());
        Assert::assertSame($body, $this->client->getRequest()->getBody()->__toString());
    }

    private static function assertCorrectRequestMethod(
        string $expected,
        string $actual,
        string $message = 'The API did not use the right HTTP method.'
    ): void {
        Assert::assertSame($expected, $actual, $message);
    }

    private static function assertCorrectRequestPath(
        string $expected,
        string $actual,
        string $message = 'The API did not request the right endpoint.'
    ): void {
        Assert::assertSame($expected, $actual, $message);
    }

    private static function assertCorrectResponseCode(
        int $expected,
        int $actual,
        string $message = 'The API did not return the right HTTP code.'
    ): void {
        Assert::assertSame($expected, $actual, $message);
    }
}

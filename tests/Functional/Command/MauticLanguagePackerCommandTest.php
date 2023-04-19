<?php

declare(strict_types=1);

namespace MauticLanguagePacker\Tests\Functional\Command;

use GuzzleHttp\Psr7\Response;
use MauticLanguagePacker\Command\MauticLanguagePackerCommand;
use MauticLanguagePacker\Tests\Common\Client\TransifexTestClient;
use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MauticLanguagePackerCommandTest extends KernelTestCase
{
    private readonly CommandTester $commandTester;

    private readonly MauticLanguagePackerCommand $command;

    private ContainerInterface $container;

    private TransifexTestClient $client;

    protected function setUp(): void
    {
        $this->container = self::getContainer();
        $parameterBag    = $this->container->get('parameter_bag');
        $eventDispatcher = $this->container->get('event_dispatcher');

        $this->client = $this->container->get('transifex.test.client');

        $application = new Application();
        $application->add(new MauticLanguagePackerCommand($parameterBag, $eventDispatcher));
        $this->command       = $application->find('mautic:language:packer');
        $this->commandTester = new CommandTester($this->command);
    }

    public function testExecute(): void
    {
        $expectedBody = <<<EOT
{
  "id": "o:some_organisation:p:some_project:r:addonbundle-flashes",
  "type": "resources",
  "attributes": {
    "slug": "addonbundle-flashes",
    "name": "AddonBundle flashes",
    "priority": "high",
    "i18n_type": "PHP_INI",
    "i18n_version": 1,
    "accept_translations": true,
    "string_count": 9,
    "word_count": 59,
    "datetime_created": "2014-12-02T20:11:50Z",
    "datetime_modified": "2015-01-12T15:25:59Z",
    "i18n_options": []
  },
  "relationships": {
    "project": {
      "links": {
        "related": "https://rest.api.transifex.com/projects/o:some_organisation:p:some_project"
      },
      "data": {
        "type": "projects",
        "id": "o:some_organisation:p:some_project"
      }
    },
    "i18n_format": {
      "data": {
        "type": "i18n_formats",
        "id": "PHP_INI"
      }
    }
  },
  "links": {
    "self": "https://rest.api.transifex.com/resources/o:some_organisation:p:some_project:r:addonbundle-flashes"
  }
}
EOT;
        $this->prepareSuccessTest(200, [], $expectedBody);
        $this->commandTester->execute(
            [
                '--upload-package' => true,
            ]
        );

        Assert::assertStringContainsString(
            '[OK] Successfully created language packages for Mautic!',
            $this->getFixedCommandOutput()
        );
    }

    private function getFixedCommandOutput(): string
    {
        return preg_replace('/  +/', ' ', str_replace(PHP_EOL, '', $this->commandTester->getDisplay()));
    }

    private function prepareSuccessTest(
        int $status = 200,
        array $headers = [],
        $body = null
    ): void {
        $this->client->setResponse(new Response($status, $headers, $body));
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

<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Command\MauticLanguagePackerCommand;
use App\Service\BuildPackageService;
use App\Service\Transifex\ResourcesService;
use App\Service\UploadPackageService;
use App\Tests\Common\Client\MockResponse;
use GuzzleHttp\Handler\MockHandler;
use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;

class MauticLanguagePackerCommandTest extends KernelTestCase
{
    private ?CommandTester $commandTester = null;

    private ?Filesystem $filesystem = null;

    private ?string $packagesDir = null;

    private ?string $translationsDir = null;

    private ?MockHandler $mockHandler = null;

    protected function setUp(): void
    {
        $container        = self::getContainer();
        $parameterBag     = $container->get('parameter_bag');
        $this->filesystem = $container->get('filesystem');

        $application = new Application();
        $application->add(
            new MauticLanguagePackerCommand(
                $parameterBag,
                $this->filesystem,
                $container->get(ResourcesService::class),
                $container->get(BuildPackageService::class),
                $container->get(UploadPackageService::class)
            )
        );
        $command             = $application->find(MauticLanguagePackerCommand::NAME);
        $this->commandTester = new CommandTester($command);

        $this->packagesDir     = $parameterBag->get('mlp.packages.dir');
        $this->translationsDir = $parameterBag->get('mlp.translations.dir');

        $this->mockHandler = $container->get('http.client.mock_handler');

        $this->backupTestTranslationsFolder();
    }

    private function backupTestTranslationsFolder(): void
    {
        $translationsBckDir = $this->getTranslationsBackupDir();
        $this->filesystem->remove($translationsBckDir);
        $this->filesystem->rename($this->translationsDir, $translationsBckDir);
        $this->filesystem->mkdir($this->translationsDir);
    }

    protected function tearDown(): void
    {
        $this->restoreTestTranslationsFolder();
        $this->removeExtraPackagesFolder();
    }

    private function restoreTestTranslationsFolder(): void
    {
        $translationsBckDir = $this->getTranslationsBackupDir();
        $this->filesystem->remove($this->translationsDir);
        $this->filesystem->rename($translationsBckDir, $this->translationsDir);
    }

    private function getTranslationsBackupDir(): string
    {
        $pathParts = explode('/', $this->translationsDir);
        array_pop($pathParts);

        return implode('/', $pathParts).'/translations-bck';
    }

    private function removeExtraPackagesFolder(): void
    {
        $packagesDirFinder = (new Finder())->depth(0)->in($this->packagesDir);

        foreach ($packagesDirFinder as $item) {
            if (
                ($item->isDir() && '20230419055736' === $item->getFilename())
                || ($item->isFile() && '20230419055736.txt' === $item->getFilename())
            ) {
                continue;
            }

            $this->filesystem->remove($item->getRealPath());
        }
    }

    public function testExecute(): void
    {
        $organisation = $_ENV['TRANSIFEX_ORGANISATION'];
        $project      = $_ENV['TRANSIFEX_PROJECT'];

        $slug     = 'addonbundle-flashes';
        $resource = 'AddonBundle flashes';
        $language = 'af';

        $headers = $this->getCommonHeaders();

        $resourcesExpectedBody = <<<EOT
{
  "data": [
    {
      "attributes": {
        "slug": "$slug",
        "name": "$resource"
      }
    }
  ]
}
EOT;
        $resourcesUri = "https://rest.api.transifex.com/resources?filter%5Bproject%5D=o%3A$organisation%3Ap%3A$project";

        $statisticsExpectedBody = <<<EOT
{
  "data": [
    {
      "id": "o:mautic:p:mautic:r:$resource:l:$language",
      "attributes": {
        "last_update": "2015-05-21T08:06:10Z"
      }
    }
  ]
}
EOT;
        $statisticsUri = "https://rest.api.transifex.com/resource_language_stats?filter%5Bresource%5D=o%3A$organisation%3Ap%3A$project%3Ar%3A$slug&filter%5Bproject%5D=o%3A$organisation%3Ap%3A$project";

        $uuid                     = 'ab267026-4109-44ef-a13f-3c369d0e8a3c';
        $translationsExpectedBody = <<<EOT
{
  "data": {
    "id": "$uuid",
    "links": {
      "self": "https://rest.api.transifex.com/resource_translations_async_downloads/$uuid"
    }
  }
}
EOT;
        $translationsUri = 'https://rest.api.transifex.com/resource_translations_async_downloads';

        $translations2ExpectedBody = 'mautic.addon.notice.reloaded="%added% addons were added, %updated% updated, and %disabled% disabled."';
        $translations2Uri          = 'https://rest.api.transifex.com/resource_translations_async_downloads/ab267026-4109-44ef-a13f-3c369d0e8a3c';

        $languagesExpectedBody = <<<EOT
{
  "data": {
    "attributes": {
      "code": "af",
      "name": "Afrikaans"
    }
  }
}
EOT;
        $languagesUri = 'https://rest.api.transifex.com/languages/l%3Aaf';

        $this->mockHandler->append(
            $this->getMockResponse(
                $resourcesExpectedBody,
                Request::METHOD_GET,
                $resourcesUri,
                $headers
            ),
            $this->getMockResponse(
                $statisticsExpectedBody,
                Request::METHOD_GET,
                $statisticsUri,
                $headers
            ),
            $this->getMockResponse(
                $translationsExpectedBody,
                Request::METHOD_POST,
                $translationsUri,
                array_merge(['Content-Length' => ['498']], $headers)
            ),
            $this->getMockResponse(
                $translations2ExpectedBody,
                Request::METHOD_GET,
                $translations2Uri,
                $headers
            ),
            $this->getMockResponse(
                $languagesExpectedBody,
                Request::METHOD_GET,
                $languagesUri,
                $headers
            )
        );

        $this->commandTester->execute([]);

        Assert::assertStringContainsString('[OK] Successfully created language packages for Mautic!', $this->getFixedCommandOutput());
    }

    private function getCommonHeaders(): array
    {
        return [
            'User-Agent'    => ['GuzzleHttp/7'],
            'Host'          => ['rest.api.transifex.com'],
            'accept'        => ['application/vnd.api+json'],
            'content-type'  => ['application/vnd.api+json'],
            'authorization' => ['Bearer not-a-real-api-token'],
        ];
    }

    private function getMockResponse(string $body, string $method, string $uri, array $headers): MockResponse
    {
        return MockResponse::fromString($body)
            ->assertRequestMethod($method)
            ->assertRequestUri($uri)
            ->assertRequestHeaders($headers);
    }

    private function getFixedCommandOutput(): string
    {
        return preg_replace('/  +/', ' ', str_replace(PHP_EOL, '', $this->commandTester->getDisplay()));
    }
}

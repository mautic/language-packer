<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Command\MauticLanguagePackerCommand;
use App\Tests\Common\Client\TransifexTestClient;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class MauticLanguagePackerCommandTest extends KernelTestCase
{
    private readonly CommandTester $commandTester;

    private readonly TransifexTestClient $client;

    private readonly Filesystem $filesystem;

    private string $packagesDir;

    private string $translationsDir;

    protected function setUp(): void
    {
        $container       = self::getContainer();
        $parameterBag    = $container->get('parameter_bag');
        $eventDispatcher = $container->get('event_dispatcher');

        $this->client = $container->get('transifex.test.client');

        $application = new Application();
        $application->add(new MauticLanguagePackerCommand($parameterBag, $eventDispatcher));
        $command             = $application->find('mautic:language:packer');
        $this->commandTester = new CommandTester($command);
        $this->filesystem    = new Filesystem();

        $this->packagesDir     = $parameterBag->get('mlp.packages.dir');
        $this->translationsDir = $parameterBag->get('mlp.translations.dir');

        $this->backupTestTranslationsFolder();
    }

    protected function tearDown(): void
    {
        $this->restoreTestTranslationsFolder();
        $this->removeExtraPackagesFolder();
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
        $this->commandTester->execute(['--upload-package' => true]);

        Assert::assertStringContainsString(
            '[OK] Successfully created language packages for Mautic!',
            $this->getFixedCommandOutput()
        );
    }

    private function getFixedCommandOutput(): string
    {
        return preg_replace('/  +/', ' ', str_replace(PHP_EOL, '', $this->commandTester->getDisplay()));
    }

    private function prepareSuccessTest(int $status = 200, array $headers = [], $body = null): void
    {
        $this->client->setResponse(new Response($status, $headers, $body));
    }

    private function backupTestTranslationsFolder(): void
    {
        $translationsBckDir = $this->getTranslationsBackupDir();
        $this->filesystem->remove($translationsBckDir);
        $this->filesystem->rename($this->translationsDir, $translationsBckDir);
        $this->filesystem->mkdir($this->translationsDir);
    }

    private function restoreTestTranslationsFolder(): void
    {
        $translationsBckDir = $this->getTranslationsBackupDir();
        $this->filesystem->remove($this->translationsDir);
        $this->filesystem->rename($translationsBckDir, $this->translationsDir);
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

    private function getTranslationsBackupDir(): string
    {
        $pathParts = explode('/', $this->translationsDir);
        array_pop($pathParts);

        return implode('/', $pathParts).'/translations-bck';
    }
}

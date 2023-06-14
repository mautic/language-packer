<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Command\MauticLanguagePackerCommand;
use App\Service\BuildPackageService;
use App\Service\FileManagerService;
use App\Service\Transifex\ResourcesService;
use App\Tests\Common\Client\MockResponse;
use App\Tests\Common\Trait\ResponseBodyBuilderTrait;
use GuzzleHttp\Handler\MockHandler;
use Mautic\Transifex\ConfigInterface;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;

class MauticLanguagePackerCommandTest extends KernelTestCase
{
    use ResponseBodyBuilderTrait;

    private CommandTester $commandTester;

    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        $container = self::getContainer();

        $application = new Application();
        $application->add(
            new MauticLanguagePackerCommand(
                $container->get(FileManagerService::class),
                $container->get(ResourcesService::class),
                $container->get(BuildPackageService::class)
            )
        );
        $command             = $application->find(MauticLanguagePackerCommand::NAME);
        $this->commandTester = new CommandTester($command);

        $this->mockHandler = $container->get(MockHandler::class);
    }

    /**
     * @dataProvider provideExecutionData
     *
     * @param ResponseInterface[]     $mockResponses
     * @param array<string, string[]> $commandArguments
     */
    public function testExecute(
        string $expectedOutput,
        array $mockResponses,
        array $commandArguments = []
    ): void {
        foreach ($mockResponses as $mockResponse) {
            $this->mockHandler->append($mockResponse);
        }

        $this->commandTester->execute($commandArguments);
        Assert::assertStringContainsString($expectedOutput, $this->getFixedCommandOutput());
    }

    /**
     * @return array<mixed>
     */
    public static function provideExecutionData(): iterable
    {
        $container       = self::getContainer();
        $translationsDir = $container->get('parameter_bag')->get('mlp.translations.dir');
        $transifexConfig = $container->get(ConfigInterface::class);

        $organisation = $transifexConfig->getOrganization();
        $project      = $transifexConfig->getProject();

        $slug     = 'addonbundle-flashes';
        $bundle   = 'AddonBundle';
        $file     = 'flashes';
        $resource = "$bundle $file";
        $language = 'af';

        $uuid = 'ab267026-4109-44ef-a13f-3c369d0e8a3c';

        yield 'get all resources generates response exception' => [
            '[ERROR] Encountered error during fetching all resources.',
            [
                self::getMockResponse(
                    'Encountered error during fetching all resources.',
                    Request::METHOD_GET,
                    "https://rest.api.transifex.com/resources?filter%5Bproject%5D=o%3A$organisation%3Ap%3A$project",
                    self::getCommonHeaders(),
                    400
                ),
            ],
        ];

        yield 'get all resources without slug and name' => [
            '[OK] Successfully created language packages for Mautic!',
            [
                self::getMockResponse(
                    self::buildResourcesBody('', ''),
                    Request::METHOD_GET,
                    "https://rest.api.transifex.com/resources?filter%5Bproject%5D=o%3A$organisation%3Ap%3A$project",
                    self::getCommonHeaders()
                ),
            ],
        ];

        yield 'fetching language statistics generates response exception' => [
            sprintf(
                '[error] Encountered error during fetching statistics for "%1$s" resource',
                $slug
            ),
            [
                self::getMockResponse(
                    self::buildResourcesBody($slug, $resource),
                    Request::METHOD_GET,
                    "https://rest.api.transifex.com/resources?filter%5Bproject%5D=o%3A$organisation%3Ap%3A$project",
                    self::getCommonHeaders()
                ),
                self::getMockResponse(
                    'Encountered error during fetching statistics.',
                    Request::METHOD_GET,
                    "https://rest.api.transifex.com/resource_language_stats?filter%5Bresource%5D=o%3A$organisation%3Ap%3A$project%3Ar%3A$slug&filter%5Bproject%5D=o%3A$organisation%3Ap%3A$project",
                    self::getCommonHeaders(),
                    400
                ),
            ],
        ];

        yield 'fetching language statistics for specific language generates response exception' => [
            sprintf(
                '[error] Encountered error during fetching statistics for "%1$s" resource of "%2$s" language.',
                $slug,
                'es'
            ),
            [
                self::getMockResponse(
                    self::buildResourcesBody($slug, $resource),
                    Request::METHOD_GET,
                    "https://rest.api.transifex.com/resources?filter%5Bproject%5D=o%3A$organisation%3Ap%3A$project",
                    self::getCommonHeaders()
                ),
                self::getMockResponse(
                    'Encountered error during fetching statistics.',
                    Request::METHOD_GET,
                    "https://rest.api.transifex.com/resource_language_stats?filter%5Bresource%5D=o%3A$organisation%3Ap%3A$project%3Ar%3A$slug&filter%5Bproject%5D=o%3A$organisation%3Ap%3A$project&filter%5Blanguage%5D=l%3Aes",
                    self::getCommonHeaders(),
                    400
                ),
            ],
            ['-l' => ['es']],
        ];

        yield 'fetching language statistics with filter languages' => [
            '[OK] Successfully created language packages for Mautic!',
            [
                self::getMockResponse(
                    self::buildResourcesBody($slug, $resource),
                    Request::METHOD_GET,
                    "https://rest.api.transifex.com/resources?filter%5Bproject%5D=o%3A$organisation%3Ap%3A$project",
                    self::getCommonHeaders()
                ),
                self::getMockResponse(
                    self::buildResourceLanguageStatsBody($resource, $language),
                    Request::METHOD_GET,
                    "https://rest.api.transifex.com/resource_language_stats?filter%5Bresource%5D=o%3A$organisation%3Ap%3A$project%3Ar%3A$slug&filter%5Bproject%5D=o%3A$organisation%3Ap%3A$project",
                    self::getCommonHeaders()
                ),
            ],
            ['-s' => [$language]],
        ];

        yield 'fetching language statistics with empty bundle and file' => [
            '[OK] Successfully created language packages for Mautic!',
            [
                self::getMockResponse(
                    self::buildResourcesBody($slug, 'AddonBundleFlashes'),
                    Request::METHOD_GET,
                    "https://rest.api.transifex.com/resources?filter%5Bproject%5D=o%3A$organisation%3Ap%3A$project",
                    self::getCommonHeaders()
                ),
            ],
        ];

        yield 'fetching language statistics with less than 40 completion percent' => [
            '[OK] Successfully created language packages for Mautic!',
            [
                self::getMockResponse(
                    self::buildResourcesBody($slug, $resource),
                    Request::METHOD_GET,
                    "https://rest.api.transifex.com/resources?filter%5Bproject%5D=o%3A$organisation%3Ap%3A$project",
                    self::getCommonHeaders()
                ),
                self::getMockResponse(
                    self::buildResourceLanguageStatsBody($resource, $language, 20),
                    Request::METHOD_GET,
                    "https://rest.api.transifex.com/resource_language_stats?filter%5Bresource%5D=o%3A$organisation%3Ap%3A$project%3Ar%3A$slug&filter%5Bproject%5D=o%3A$organisation%3Ap%3A$project",
                    self::getCommonHeaders()
                ),
            ],
        ];

        yield 'translation download generates response exception' => [
            'failed with response 400: Encountered error during translation',
            [
                self::getMockResponse(
                    self::buildResourcesBody($slug, $resource),
                    Request::METHOD_GET,
                    "https://rest.api.transifex.com/resources?filter%5Bproject%5D=o%3A$organisation%3Ap%3A$project",
                    self::getCommonHeaders()
                ),
                self::getMockResponse(
                    self::buildResourceLanguageStatsBody($resource, $language),
                    Request::METHOD_GET,
                    "https://rest.api.transifex.com/resource_language_stats?filter%5Bresource%5D=o%3A$organisation%3Ap%3A$project%3Ar%3A$slug&filter%5Bproject%5D=o%3A$organisation%3Ap%3A$project",
                    self::getCommonHeaders()
                ),
                self::getMockResponse(
                    'Encountered error during translation',
                    Request::METHOD_POST,
                    'https://rest.api.transifex.com/resource_translations_async_downloads',
                    array_merge(['Content-Length' => ['498']], self::getCommonHeaders()),
                    400
                ),
                self::getMockResponse(
                    self::buildLanguagesBody($language),
                    Request::METHOD_GET,
                    "https://rest.api.transifex.com/languages/l%3A$language",
                    self::getCommonHeaders()
                ),
            ],
        ];

        yield 'translation download generates response exception on fulfillPromises' => [
            sprintf('[error] Translation download for %1$s failed', "$translationsDir/$language/$bundle/$file.ini"),
            [
                self::getMockResponse(
                    self::buildResourcesBody($slug, $resource),
                    Request::METHOD_GET,
                    "https://rest.api.transifex.com/resources?filter%5Bproject%5D=o%3A$organisation%3Ap%3A$project",
                    self::getCommonHeaders()
                ),
                self::getMockResponse(
                    self::buildResourceLanguageStatsBody($resource, $language),
                    Request::METHOD_GET,
                    "https://rest.api.transifex.com/resource_language_stats?filter%5Bresource%5D=o%3A$organisation%3Ap%3A$project%3Ar%3A$slug&filter%5Bproject%5D=o%3A$organisation%3Ap%3A$project",
                    self::getCommonHeaders()
                ),
                self::getMockResponse(
                    self::buildTranslationsBody($uuid),
                    Request::METHOD_POST,
                    'https://rest.api.transifex.com/resource_translations_async_downloads',
                    array_merge(['Content-Length' => ['498']], self::getCommonHeaders())
                ),
                self::getMockResponse(
                    self::buildTranslationsBody($uuid, 'failed'),
                    Request::METHOD_GET,
                    "https://rest.api.transifex.com/resource_translations_async_downloads/$uuid",
                    self::getCommonHeaders(),
                    400
                ),
                self::getMockResponse(
                    self::buildLanguagesBody($language),
                    Request::METHOD_GET,
                    "https://rest.api.transifex.com/languages/l%3A$language",
                    self::getCommonHeaders()
                ),
            ],
        ];

        $ini = ";mautic.addon.notice.reloaded=some_translation\n";
        $ini .= "mautic.addon.notice.reloaded=some_translation\n";
        $ini .= "no=\"some_translation\"\n";
        $ini .= "mautic.addon.notice.reloaded=\"some_translation\"\\\\\n";
        $ini .= "mautic.addon.notice.reloaded='duplicate2'\n";

        yield 'translation download generates invalid ini file' => [
            sprintf(
                '[error] Encountered error during "%1$s" download.',
                "$translationsDir/$language/$bundle/$file.ini"
            ),
            [
                self::getMockResponse(
                    self::buildResourcesBody($slug, $resource),
                    Request::METHOD_GET,
                    "https://rest.api.transifex.com/resources?filter%5Bproject%5D=o%3A$organisation%3Ap%3A$project",
                    self::getCommonHeaders()
                ),
                self::getMockResponse(
                    self::buildResourceLanguageStatsBody($resource, $language),
                    Request::METHOD_GET,
                    "https://rest.api.transifex.com/resource_language_stats?filter%5Bresource%5D=o%3A$organisation%3Ap%3A$project%3Ar%3A$slug&filter%5Bproject%5D=o%3A$organisation%3Ap%3A$project",
                    self::getCommonHeaders()
                ),
                self::getMockResponse(
                    self::buildTranslationsBody($uuid),
                    Request::METHOD_POST,
                    'https://rest.api.transifex.com/resource_translations_async_downloads',
                    array_merge(['Content-Length' => ['498']], self::getCommonHeaders())
                ),
                self::getMockResponse(
                    self::buildIniBody($ini),
                    Request::METHOD_GET,
                    "https://rest.api.transifex.com/resource_translations_async_downloads/$uuid",
                    self::getCommonHeaders()
                ),
                self::getMockResponse(
                    self::buildLanguagesBody($language),
                    Request::METHOD_GET,
                    "https://rest.api.transifex.com/languages/l%3A$language",
                    self::getCommonHeaders()
                ),
            ],
        ];

        yield 'fetching language details generates response exception' => [
            sprintf('Encountered error during fetching language "%1$s" details for package build.', $language),
            [
                self::getMockResponse(
                    self::buildResourcesBody($slug, $resource),
                    Request::METHOD_GET,
                    "https://rest.api.transifex.com/resources?filter%5Bproject%5D=o%3A$organisation%3Ap%3A$project",
                    self::getCommonHeaders()
                ),
                self::getMockResponse(
                    self::buildResourceLanguageStatsBody($resource, $language),
                    Request::METHOD_GET,
                    "https://rest.api.transifex.com/resource_language_stats?filter%5Bresource%5D=o%3A$organisation%3Ap%3A$project%3Ar%3A$slug&filter%5Bproject%5D=o%3A$organisation%3Ap%3A$project",
                    self::getCommonHeaders()
                ),
                self::getMockResponse(
                    self::buildTranslationsBody($uuid),
                    Request::METHOD_POST,
                    'https://rest.api.transifex.com/resource_translations_async_downloads',
                    array_merge(['Content-Length' => ['498']], self::getCommonHeaders())
                ),
                self::getMockResponse(
                    self::buildIniBody(),
                    Request::METHOD_GET,
                    "https://rest.api.transifex.com/resource_translations_async_downloads/$uuid",
                    self::getCommonHeaders()
                ),
                self::getMockResponse(
                    'Encountered error during fetching language.',
                    Request::METHOD_GET,
                    "https://rest.api.transifex.com/languages/l%3A$language",
                    self::getCommonHeaders(),
                    400
                ),
            ],
        ];

        yield 'build package with filter languages' => [
            '[OK] Successfully created language packages for Mautic!',
            [
                self::getMockResponse(
                    self::buildResourcesBody($slug, $resource),
                    Request::METHOD_GET,
                    "https://rest.api.transifex.com/resources?filter%5Bproject%5D=o%3A$organisation%3Ap%3A$project",
                    self::getCommonHeaders()
                ),
                self::getMockResponse(
                    self::buildResourceLanguageStatsBody($resource, $language),
                    Request::METHOD_GET,
                    "https://rest.api.transifex.com/resource_language_stats?filter%5Bresource%5D=o%3A$organisation%3Ap%3A$project%3Ar%3A$slug&filter%5Bproject%5D=o%3A$organisation%3Ap%3A$project",
                    self::getCommonHeaders()
                ),
                self::getMockResponse(
                    self::buildTranslationsBody($uuid),
                    Request::METHOD_POST,
                    'https://rest.api.transifex.com/resource_translations_async_downloads',
                    array_merge(['Content-Length' => ['498']], self::getCommonHeaders())
                ),
                self::getMockResponse(
                    self::buildIniBody(),
                    Request::METHOD_GET,
                    "https://rest.api.transifex.com/resource_translations_async_downloads/$uuid",
                    self::getCommonHeaders()
                ),
                self::getMockResponse(
                    self::buildLanguagesBody($language),
                    Request::METHOD_GET,
                    "https://rest.api.transifex.com/languages/l%3A$language",
                    self::getCommonHeaders()
                ),
            ],
            ['-s' => ['en']],
        ];

        yield 'success with filter languages' => [
            '[OK] Successfully created language packages for Mautic!',
            [
                self::getMockResponse(
                    self::buildResourcesBody($slug, $resource),
                    Request::METHOD_GET,
                    "https://rest.api.transifex.com/resources?filter%5Bproject%5D=o%3A$organisation%3Ap%3A$project",
                    self::getCommonHeaders()
                ),
                self::getMockResponse(
                    self::buildResourceLanguageStatsBody($resource, $language),
                    Request::METHOD_GET,
                    "https://rest.api.transifex.com/resource_language_stats?filter%5Bresource%5D=o%3A$organisation%3Ap%3A$project%3Ar%3A$slug&filter%5Bproject%5D=o%3A$organisation%3Ap%3A$project",
                    self::getCommonHeaders()
                ),
                self::getMockResponse(
                    self::buildTranslationsBody($uuid),
                    Request::METHOD_POST,
                    'https://rest.api.transifex.com/resource_translations_async_downloads',
                    array_merge(['Content-Length' => ['498']], self::getCommonHeaders())
                ),
                self::getMockResponse(
                    self::buildIniBody(),
                    Request::METHOD_GET,
                    "https://rest.api.transifex.com/resource_translations_async_downloads/$uuid",
                    self::getCommonHeaders()
                ),
                self::getMockResponse(
                    self::buildLanguagesBody($language),
                    Request::METHOD_GET,
                    "https://rest.api.transifex.com/languages/l%3A$language",
                    self::getCommonHeaders()
                ),
            ],
            ['-s' => ['es', 'en']],
        ];
    }

    public function testPackageZipFolderStructure(): void
    {
        $container       = self::getContainer();
        $transifexConfig = $container->get(ConfigInterface::class);
        $parameterBag    = $container->get('parameter_bag');
        $packagesDir     = $parameterBag->get('mlp.packages.dir');

        $organisation = $transifexConfig->getOrganization();
        $project      = $transifexConfig->getProject();

        $slug     = 'addonbundle-flashes';
        $bundle   = 'AddonBundle';
        $file     = 'flashes';
        $resource = "$bundle $file";
        $language = 'af';

        $uuid = 'ab267026-4109-44ef-a13f-3c369d0e8a3c';

        $mockResponses = [
            self::getMockResponse(
                self::buildResourcesBody($slug, $resource),
                Request::METHOD_GET,
                "https://rest.api.transifex.com/resources?filter%5Bproject%5D=o%3A$organisation%3Ap%3A$project",
                self::getCommonHeaders()
            ),
            self::getMockResponse(
                self::buildResourceLanguageStatsBody($resource, $language),
                Request::METHOD_GET,
                "https://rest.api.transifex.com/resource_language_stats?filter%5Bresource%5D=o%3A$organisation%3Ap%3A$project%3Ar%3A$slug&filter%5Bproject%5D=o%3A$organisation%3Ap%3A$project",
                self::getCommonHeaders()
            ),
            self::getMockResponse(
                self::buildTranslationsBody($uuid),
                Request::METHOD_POST,
                'https://rest.api.transifex.com/resource_translations_async_downloads',
                array_merge(['Content-Length' => ['498']], self::getCommonHeaders())
            ),
            self::getMockResponse(
                self::buildIniBody(),
                Request::METHOD_GET,
                "https://rest.api.transifex.com/resource_translations_async_downloads/$uuid",
                self::getCommonHeaders()
            ),
            self::getMockResponse(
                self::buildLanguagesBody($language),
                Request::METHOD_GET,
                "https://rest.api.transifex.com/languages/l%3A$language",
                self::getCommonHeaders()
            ),
        ];

        foreach ($mockResponses as $mockResponse) {
            $this->mockHandler->append($mockResponse);
        }

        $this->commandTester->execute(['-s' => ['es', 'en']]);
        Assert::assertStringContainsString('[OK] Successfully created language packages for Mautic!', $this->getFixedCommandOutput());

        $packagesDirFinder = (new Finder())->in($packagesDir)->files()->name('*.zip');
        $zipArchive        = new \ZipArchive();

        $expectedFolderStructure = [
            'af/AddonBundle',
            'af/AddonBundle/flashes.ini',
            'af/config.json',
            'af/config.php',
        ];
        $cnt = 0;

        foreach ($packagesDirFinder as $file) {
            if (true === $zipArchive->open($file->getRealPath())) {
                $zipArchive->extractTo($packagesDir.'/'.$file->getRelativePath());
                $zipArchive->close();

                $extractedLanguageFinder = (new Finder())->in($packagesDir.'/'.$file->getRelativePath().'/'.$language);

                foreach ($extractedLanguageFinder as $extractedFile) {
                    Assert::assertStringContainsString($expectedFolderStructure[$cnt++], $extractedFile->getRealPath());
                }
            }
        }

        Assert::assertSame(count($expectedFolderStructure), $cnt);
    }

    /**
     * @return array<string, array<string>>
     */
    private static function getCommonHeaders(): array
    {
        return [
            'User-Agent'    => ['GuzzleHttp/7'],
            'Host'          => ['rest.api.transifex.com'],
            'accept'        => ['application/vnd.api+json'],
            'content-type'  => ['application/vnd.api+json'],
            'authorization' => ['Bearer not-a-real-api-token'],
        ];
    }

    /**
     * @param array<string, array<string>> $headers
     * @param array<string, array<string>> $responseHeaders
     */
    private static function getMockResponse(
        string $body,
        string $method,
        string $uri,
        array $headers = [],
        int $status = 200,
        array $responseHeaders = []
    ): MockResponse {
        return MockResponse::fromString($body, $status, $responseHeaders)
            ->assertRequestMethod($method)
            ->assertRequestUri($uri)
            ->assertRequestHeaders($headers);
    }

    private function getFixedCommandOutput(): string
    {
        return preg_replace('/  +/', ' ', str_replace(PHP_EOL, '', $this->commandTester->getDisplay()));
    }

    protected function tearDown(): void
    {
        $container = self::getContainer();

        $parameterBag = $container->get('parameter_bag');
        $filesystem   = $container->get('filesystem');

        $filesystem->remove($parameterBag->get('mlp.translations.dir'));
        $filesystem->remove($parameterBag->get('mlp.packages.dir'));
    }
}

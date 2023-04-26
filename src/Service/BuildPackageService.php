<?php

declare(strict_types=1);

namespace App\Service;

use App\Service\Transifex\Connector\Languages;
use App\Service\Transifex\DTO\PackageDTO;
use Mautic\Transifex\Exception\ResponseException;
use Mautic\Transifex\TransifexInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class BuildPackageService
{
    public function __construct(private readonly TransifexInterface $transifex, private readonly Filesystem $filesystem)
    {
    }

    public function build(PackageDTO $packageDTO, ConsoleLogger $logger): int
    {
        $translationsDirFinder = (new Finder())->sortByName()
            ->depth(0)
            ->directories()
            ->in($packageDTO->translationsDir);

        $languageData = [];

        $error = 0;

        foreach ($translationsDirFinder as $folder) {
            $languageCode = $folder->getBasename();
            $languageDir  = $packageDTO->translationsDir.'/'.$languageCode;

            if (in_array($languageCode, $packageDTO->skipLanguages, true)) {
                continue;
            }

            // If the directory is empty, there is no point in packaging it
            if (count(scandir($languageDir)) < 2) {
                continue;
            }

            try {
                $languageDetails    = $this->transifex->getConnector(Languages::class);
                $response           = $languageDetails->getLanguageDetails($languageCode);
                $statistics         = json_decode($response->getBody()->__toString(), true);
                $languageAttributes = $statistics['data']['attributes'] ?? [];
            } catch (ResponseException $exception) {
                $logger->error(
                    sprintf(
                        'Encountered error during fetching language "%1$s" details for package build. Error: %2$s',
                        $languageCode,
                        $exception->getMessage()
                    )
                );
                $error = 1;

                continue;
            }

            if (empty($languageAttributes)) {
                continue;
            }

            $code           = $languageAttributes['code'] ?? '';
            $name           = $languageAttributes['name'] ?? '';
            $languageData[] = ['name' => $name, 'code' => $code];

            $packageMetadata = ['name' => $name, 'locale' => $code, 'author' => 'Mautic Translators'];
            $configData      = $this->renderConfig($packageMetadata);

            $this->filesystem->dumpFile($languageDir.'/config.php', $configData);
            $this->filesystem->dumpFile($languageDir.'/config.json', json_encode($packageMetadata)."\n");

            // Hack so we produce exactly the same zip file on each run
            $this->produceSameZipEachTime($languageDir);

            $this->createZipPackage($languageDir, $packageDTO->packagesTimestampDir, $languageCode);

            // Store the metadata file outside the zip too for easier manipulation with scripts
            $this->filesystem->copy(
                $languageDir.'/config.json',
                $packageDTO->packagesTimestampDir.'/'.$languageCode.'.json'
            );

            $logger->info(sprintf('Creating package for "%1$s" language.', $languageDir));
        }

        // Store the lang data as a backup
        $this->filesystem->dumpFile(
            $packageDTO->packagesTimestampDir.'.txt',
            json_encode($languageData, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)
        );

        return $error;
    }

    /**
     * @param array<string, string> $data
     */
    private function renderConfig(array $data): string
    {
        $string = "<?php\n";
        $string .= "\$config = [\n";

        foreach ($data as $key => $value) {
            $string .= "\t'$key' => '$value',\n";
        }

        $string .= "];\n\nreturn \$config;";

        return $string;
    }

    private function produceSameZipEachTime(string $languageDir): void
    {
        $this->filesystem->touch($languageDir.'/config.php', strtotime('2019-01-01'));
        $this->filesystem->touch($languageDir.'/config.json', strtotime('2019-01-01'));
        $this->filesystem->touch($languageDir, strtotime('2019-01-01'));
    }

    private function createZipPackage(string $languageDir, string $packagesTimestampDir, string $languageCode): void
    {
        $zipArchive        = new \ZipArchive();
        $languageDirFinder = (new Finder())->sortByName()->in($languageDir)->files();

        if ($zipArchive->open($languageDir.'.zip', \ZipArchive::CREATE)) {
            foreach ($languageDirFinder as $file) {
                $zipArchive->addFile($file->getPathname(), $file->getBasename());
            }

            $zipArchive->close();
        }

        $this->filesystem->rename($languageDir.'.zip', $packagesTimestampDir.'/'.$languageCode.'.zip');
    }
}

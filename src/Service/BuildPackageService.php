<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\BuildPackageException;
use App\Service\Transifex\Connector\Languages;
use App\Service\Transifex\DTO\PackageDTO;
use Mautic\Transifex\Exception\ResponseException;
use Mautic\Transifex\TransifexInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

class BuildPackageService
{
    public function __construct(private readonly TransifexInterface $transifex, private readonly Filesystem $filesystem)
    {
    }

    public function build(PackageDTO $packageDTO, LoggerInterface $logger): void
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
                $statistics         = json_decode((string) $response->getBody(), true);
                $languageAttributes = $statistics['data']['attributes'] ?? [];
            } catch (ResponseException $exception) {
                $logger->error(
                    sprintf(
                        'Encountered error during fetching language "%1$s" details for package build. Error: %2$s',
                        $languageCode,
                        $exception->getMessage()
                    )
                );
                ++$error;

                continue;
            }

            if (empty($languageAttributes)) {
                continue;
            }

            $languageData[] = $this->doBuild($logger, $packageDTO, $languageAttributes, $languageDir, $languageCode);
        }

        // Store the lang data as a backup
        $this->filesystem->dumpFile(
            $packageDTO->packagesTimestampDir.'.txt',
            json_encode($languageData, JSON_PRETTY_PRINT)
        );

        if ($error) {
            throw new BuildPackageException("Created language packages for Mautic with {$error} errors. Check logs for details");
        }
    }

    /**
     * @param array<string, array<string|int>> $languageAttributes
     *
     * @return array<string, string>
     */
    private function doBuild(
        LoggerInterface $logger,
        PackageDTO $packageDTO,
        array $languageAttributes,
        string $languageDir,
        string $languageCode
    ): array {
        $code = $languageAttributes['code'] ?? '';
        $name = $languageAttributes['name'] ?? '';

        $packageMetadata = ['name' => $name, 'locale' => $code, 'author' => 'Mautic Translators'];
        $configData      = $this->renderConfig($packageMetadata);

        $this->filesystem->dumpFile($languageDir.'/config.php', $configData);
        $this->filesystem->dumpFile($languageDir.'/config.json', json_encode($packageMetadata)."\n");

        // Hack so we produce exactly the same zip file on each run
        $this->produceSameZipEachTime($languageDir);

        $this->createZipPackage($packageDTO->translationsDir, $packageDTO->packagesTimestampDir, $languageCode);

        // Store the metadata file outside the zip too for easier manipulation with scripts
        $this->filesystem->copy(
            $languageDir.'/config.json',
            $packageDTO->packagesTimestampDir.'/'.$languageCode.'.json'
        );

        $logger->info(sprintf('Creating package for "%1$s" language.', $languageDir));

        return ['name' => $name, 'code' => $code];
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

    private function createZipPackage(string $translationsDir, string $packagesTimestampDir, string $languageCode): void
    {
        chdir($translationsDir);

        $command = "find $languageCode/ -mindepth 1 | sort | zip -X ";
        $command .= $packagesTimestampDir.'/'.$languageCode.'.zip -@ > /dev/null';

        $process = Process::fromShellCommandline($command);
        $process->run();

        $lastLine = $process->getOutput();
        $status   = $process->getExitCode();

        if ($status) {
            if ($lastLine) {
                throw new BuildPackageException($lastLine, $status);
            }

            throw new BuildPackageException(sprintf('Unknown error executing "%1$s" command', $command), $status);
        }
    }
}

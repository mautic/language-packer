<?php

declare(strict_types=1);

namespace MauticLanguagePacker\EventSubscriber;

use Mautic\Transifex\TransifexInterface;
use MauticLanguagePacker\Event\CreatePackageEvent;
use MauticLanguagePacker\Transifex\Connector\Languages;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class CreatePackageSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TransifexInterface $transifex,
        private readonly Filesystem $filesystem
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [CreatePackageEvent::NAME => 'createPackage'];
    }

    public function createPackage(CreatePackageEvent $event): void
    {
        $io                   = $event->getIo();
        $filterLanguages      = $event->getFilterLanguages();
        $translationsDir      = $event->getTranslationsDir();
        $packagesTimestampDir = $event->getPackagesTimestampDir();

        $translationsDirFinder = (new Finder())->sortByName()->depth(0)->directories()->in($translationsDir);

        $languageData = [];

        foreach ($translationsDirFinder as $folder) {
            $languageCode = $folder->getBasename();
            $languageDir  = $translationsDir.'/'.$languageCode;

            if (in_array($languageCode, $filterLanguages, true)) {
                continue;
            }

            // If the directory is empty, there is no point in packaging it
            if (count(scandir($languageDir)) < 2) {
                continue;
            }

            $languageDetails    = $this->transifex->getConnector(Languages::class);
            $response           = $languageDetails->getLanguageDetails($languageCode);
            $statistics         = json_decode($response->getBody()->__toString(), true, 512, JSON_THROW_ON_ERROR);
            $languageAttributes = $statistics['data']['attributes'] ?? [];

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

            $this->createZipPackage($languageDir, $packagesTimestampDir, $languageCode);

            // Store the metadata file outside the zip too for easier manipulation with scripts
            $this->filesystem->copy($languageDir.'/config.json', $packagesTimestampDir.'/'.$languageCode.'.json');

            $io->writeln('<info>'.sprintf('Creating package for "%s" language.', $languageDir).'</info>');
        }

        // Store the lang data as a backup
        $this->filesystem->dumpFile(
            $packagesTimestampDir.'.txt',
            json_encode($languageData, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)
        );
    }

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

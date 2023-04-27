<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\BuildPackageService;
use App\Service\FileManagerService;
use App\Service\Transifex\DTO\PackageDTO;
use App\Service\Transifex\DTO\ResourceDTO;
use App\Service\Transifex\DTO\UploadPackageDTO;
use App\Service\Transifex\ResourcesService;
use App\Service\UploadPackageService;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: self::NAME, description: 'Creates language packages for Mautic releases')]
class MauticLanguagePackerCommand extends Command
{
    public const NAME = 'mautic:language:packer';

    public function __construct(
        private readonly FileManagerService $fileManagerService,
        private readonly ResourcesService $resourcesService,
        private readonly BuildPackageService $buildPackageService,
        private readonly UploadPackageService $uploadPackageService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'skip-languages',
            's',
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'Skip languages. For e.g. bin/console '.self::NAME.' -s es -s en.'
        );
        $this->addOption(
            'languages',
            'l',
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'Process languages. For e.g. bin/console '.self::NAME.' -l es -l en.'
        );
        $this->addOption(
            'upload-package',
            'u',
            InputOption::VALUE_NONE,
            'Upload the package to AWS S3. For e.g. bin/console '.self::NAME.' -u.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $logger = $this->getConsoleLogger($output);

        $skipLanguages = $input->getOption('skip-languages');
        $languages     = $input->getOption('languages');
        $uploadPackage = $input->getOption('upload-package');

        // Remove any previous pulls and rebuild the translations folder
        $translationsDir = $this->fileManagerService->initTranslationsDir();

        // Fetch the project resources now and store them locally
        $resourceDTO   = new ResourceDTO($translationsDir, $skipLanguages, $languages);
        $resourceError = $this->resourcesService->processAllResources($resourceDTO, $logger);

        if ($resourceError) {
            $io->error('Encountered error during fetching all resources.');

            return Command::FAILURE;
        }

        // Now we start building our ZIP archives
        $packagesTimestampDir = $this->fileManagerService->initPackagesDir();

        // Compile our data to forward to mautic.org and build the ZIP packages
        $packageDTO = new PackageDTO($translationsDir, $skipLanguages, $packagesTimestampDir);
        $buildError = $this->buildPackageService->build($packageDTO, $logger);

        if ($buildError) {
            $io->warning('Created language packages for Mautic with some errors.');
        } else {
            $io->success('Successfully created language packages for Mautic!');
        }

        // If instructed, upload the packages
        if ($uploadPackage) {
            $io->info('Starting package upload to AWS S3.');
            $uploadPackageDTO = new UploadPackageDTO($packagesTimestampDir);
            $uploadError      = $this->uploadPackageService->uploadPackage($uploadPackageDTO, $logger);

            if ($uploadError) {
                $io->error('Encountered error during language packages upload to AWS S3.');
            } else {
                $io->success('Successfully uploaded language packages to AWS S3!');
            }
        }

        return Command::SUCCESS;
    }

    private function getConsoleLogger(OutputInterface $output): ConsoleLogger
    {
        $verbosityLevelMap = [
            LogLevel::ERROR => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::INFO  => OutputInterface::VERBOSITY_NORMAL,
        ];

        return new ConsoleLogger($output, $verbosityLevelMap);
    }
}

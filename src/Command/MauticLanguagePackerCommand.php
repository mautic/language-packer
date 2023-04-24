<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\BuildPackageService;
use App\Service\Transifex\DTO\PackageDTO;
use App\Service\Transifex\DTO\ResourceDTO;
use App\Service\Transifex\DTO\UploadPackageDTO;
use App\Service\Transifex\ResourcesService;
use App\Service\UploadPackageService;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(name: self::NAME, description: 'Creates language packages for Mautic releases')]
class MauticLanguagePackerCommand extends Command
{
    public const NAME = 'mautic:language:packer';

    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly Filesystem $filesystem,
        private readonly ResourcesService $resourcesService,
        private readonly BuildPackageService $buildPackageService,
        private readonly UploadPackageService $uploadPackageService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'filter-languages',
            InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
            'Languages that you want to skip (separate multiple languages with a space)'
        )->addOption(
            'language',
            null,
            InputOption::VALUE_REQUIRED,
            'Do you want to process a single language? Add e.g. `--language es` as argument.'
        )->addOption(
            'upload-package',
            null,
            InputOption::VALUE_NONE,
            'Do you want to upload the package to AWS S3? Add --upload-package as argument.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $logger = $this->getConsoleLogger($output);

        $packagesDir     = $this->parameterBag->get('mlp.packages.dir');
        $translationsDir = $this->parameterBag->get('mlp.translations.dir');

        $filterLanguages = $input->getArgument('filter-languages');
        $language        = $input->getOption('language') ?? '';
        $uploadPackage   = $input->getOption('upload-package');

        // Remove any previous pulls and rebuild the translations folder
        $this->filesystem->remove($translationsDir);
        $this->filesystem->mkdir($translationsDir);

        // Fetch the project resources now and store them locally
        $resourceDTO   = new ResourceDTO($translationsDir, $filterLanguages, $language);
        $resourceError = $this->resourcesService->processAllResources($resourceDTO, $logger);

        if ($resourceError) {
            $io->error('Encountered error during fetching all resources.');

            return Command::FAILURE;
        }

        // Now we start building our ZIP archives
        $this->filesystem->mkdir($packagesDir);

        // Add a folder for our current build
        $timestamp            = (new \DateTime())->format('YmdHis');
        $packagesTimestampDir = $packagesDir.'/'.$timestamp;
        $this->filesystem->mkdir($packagesTimestampDir);

        // Compile our data to forward to mautic.org and build the ZIP packages
        $packageDTO = new PackageDTO($translationsDir, $filterLanguages, $packagesTimestampDir);
        $buildError = $this->buildPackageService->build($packageDTO, $logger);

        if ($buildError) {
            $io->warning('Created language packages for Mautic with some errors.');
        } else {
            $io->success('Successfully created language packages for Mautic!');
        }

        // If instructed, upload the packages
        if ($uploadPackage) {
            $io->info('Starting package upload to AWS S3.');
            $uploadPackageDTO = new UploadPackageDTO($packagesTimestampDir, $_ENV['AWS_S3_BUCKET']);
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
        $formatLevelMap = [
            LogLevel::CRITICAL => ConsoleLogger::ERROR,
            LogLevel::DEBUG    => ConsoleLogger::INFO,
        ];

        return new ConsoleLogger($output, [], $formatLevelMap);
    }
}

<?php

declare(strict_types=1);

namespace MauticLanguagePacker\Command;

use MauticLanguagePacker\Event\CreatePackageEvent;
use MauticLanguagePacker\Event\PrepareDirEvent;
use MauticLanguagePacker\Event\ResourceEvent;
use MauticLanguagePacker\Event\UploadPackageEvent;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[AsCommand(name: self::NAME, description: 'Creates language packages for Mautic releases')]
class MauticLanguagePackerCommand extends Command
{
    public const NAME = 'mautic:language:packer';

    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly EventDispatcherInterface $eventDispatcher
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
            'upload-package',
            null,
            InputOption::VALUE_NONE,
            'Do you want to upload the package to AWS S3? Add --upload-package as argument.'
        )->addOption(
            'language',
            null,
            InputOption::VALUE_REQUIRED,
            'Do you want to process a single language? Add e.g. `--language es` as argument.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (
            !$this->parameterBag->get('mlp.transifex.api.token')
            || !$this->parameterBag->get('mlp.transifex.organisation')
            || !$this->parameterBag->get('mlp.transifex.project')
        ) {
            $io->error('Add TRANSIFEX_API_TOKEN, TRANSIFEX_ORGANISATION and TRANSIFEX_PROJECT in .env.');

            return Command::FAILURE;
        }

        $translationsDir = $this->parameterBag->get('mlp.translations.dir');
        $packagesDir     = $this->parameterBag->get('mlp.packages.dir');

        $filterLanguages = $input->getArgument('filter-languages');
        $uploadPackage   = $input->getOption('upload-package');
        $language        = $input->getOption('language') ?? '';

        // Remove any previous pulls and rebuild the translations folder
        $translationsDirEvent = new PrepareDirEvent($translationsDir, true);
        $this->eventDispatcher->dispatch($translationsDirEvent, PrepareDirEvent::NAME);

        // Fetch the project resources now and store them locally
        $resourceEvent = new ResourceEvent($io, $filterLanguages, $translationsDir, $language);
        $this->eventDispatcher->dispatch($resourceEvent, ResourceEvent::NAME);

        // Now we start building our ZIP archives
        $packagesDirEvent = new PrepareDirEvent($packagesDir);
        $this->eventDispatcher->dispatch($packagesDirEvent, PrepareDirEvent::NAME);

        // Add a folder for our current build
        $timestamp            = (new \DateTime())->format('YmdHis');
        $packagesTimestampDir = $packagesDir.'/'.$timestamp;
        $packages2DirEvent    = new PrepareDirEvent($packagesTimestampDir);
        $this->eventDispatcher->dispatch($packages2DirEvent, PrepareDirEvent::NAME);

        // Compile our data to forward to mautic.org and build the ZIP packages
        $createPackageEvent = new CreatePackageEvent($io, $filterLanguages, $translationsDir, $packagesTimestampDir);
        $this->eventDispatcher->dispatch($createPackageEvent, CreatePackageEvent::NAME);

        $errorsFile = $translationsDir.'/errors.txt';

        if (file_exists($errorsFile)) {
            $io->warning(
                sprintf('Created language packages for Mautic. Check %1$s as there were errors!', $errorsFile)
            );
        } else {
            $io->success('Successfully created language packages for Mautic!');
        }

        // If instructed, upload the packages
        if ($uploadPackage) {
            $io->info('Starting package upload to AWS S3, checking credentials.');
            if (
                !$this->parameterBag->get('mlp.aws.key')
                || !$this->parameterBag->get('mlp.aws.secret')
                || !$this->parameterBag->get('mlp.aws.region')
                || !$this->parameterBag->get('mlp.aws.bucket')
            ) {
                $io->error('Add AWS_KEY, AWS_SECRET, AWS_REGION and AWS_BUCKET in .env.');

                return Command::FAILURE;
            }

            $uploadPackageEvent = new UploadPackageEvent(
                $io, $packagesTimestampDir, $this->parameterBag->get('mlp.aws.bucket')
            );
            $this->eventDispatcher->dispatch($uploadPackageEvent, UploadPackageEvent::NAME);

            $io->success('Successfully uploaded language packages to AWS S3!');
        }

        return Command::SUCCESS;
    }
}

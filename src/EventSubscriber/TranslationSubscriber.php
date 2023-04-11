<?php

declare(strict_types=1);

namespace MauticLanguagePacker\EventSubscriber;

use Mautic\Transifex\Connector\Translations;
use Mautic\Transifex\Exception\ResponseException;
use Mautic\Transifex\Promise;
use Mautic\Transifex\TransifexInterface;
use MauticLanguagePacker\Event\PrepareDirEvent;
use MauticLanguagePacker\Event\TranslationEvent;
use MauticLanguagePacker\Exception\InvalidFileException;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Filesystem;

class TranslationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TransifexInterface $transifex,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Filesystem $filesystem
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [TranslationEvent::NAME => 'getTranslations'];
    }

    public function getTranslations(TranslationEvent $event): void
    {
        $io             = $event->getIo();
        $translationDTO = $event->getTranslationDTO();

        $slug            = $translationDTO->getSlug();
        $language        = $translationDTO->getLanguage();
        $translationsDir = $translationDTO->getTranslationsDir();
        $bundle          = $translationDTO->getBundle();
        $file            = $translationDTO->getFile();
        $lastUpdate      = $translationDTO->getLastUpdate();

        $bundlePath = $translationsDir.'/'.$language.'/'.$bundle;
        $filePath   = $bundlePath.'/'.$file.'.ini';
        $errorsFile = $translationsDir.'/errors.txt';

        $prepareDirEvent = new PrepareDirEvent($bundlePath);
        $this->eventDispatcher->dispatch($prepareDirEvent, PrepareDirEvent::NAME);

        // Set the timestamp on the file so our zip builds are reproducible
        $this->filesystem->touch($bundlePath, strtotime($lastUpdate));
        $this->filesystem->touch($filePath, strtotime($lastUpdate));

        $maxAttempts = 3;

        for ($attempt = 1; $attempt <= $maxAttempts; ++$attempt) {
            try {
                $translations = $this->transifex->getConnector(Translations::class);
                $response     = $translations->download($slug, $language);
                $promise      = $this->transifex->getApiConnector()->createPromise($response);
                $promise->setFilePath($filePath);
                $this->fulfillPromises($promise, $io);
                $this->ensureFileValid($promise->getFilePath());
                break;
            } catch (ResponseException|InvalidFileException $exception) {
                if ($exception instanceof InvalidFileException) {
                    $attempt = $maxAttempts;
                }

                if ($attempt === $maxAttempts) {
                    $this->filesystem->appendToFile(
                        $errorsFile,
                        sprintf(
                            'Encountered error during %1$s download. Error: %2$s.'.PHP_EOL.PHP_EOL,
                            $filePath,
                            $exception->getMessage()
                        )
                    );
                    $io->error(
                        sprintf(
                            'Encountered error during %1$s download. Check %2$s!',
                            $filePath,
                            $errorsFile
                        )
                    );
                }

                sleep(2 ** $attempt);
            }
        }
    }

    private function fulfillPromises(Promise $promise, SymfonyStyle $io): void
    {
        $promises = new \SplQueue();
        $promises->enqueue($promise);

        usleep(500000);

        $this->transifex->getApiConnector()->fulfillPromises(
            $promises,
            function (ResponseInterface $response) use (
                &$translationContent,
                $promise,
                $io
            ) {
                $filePath           = $promise->getFilePath();
                $translationContent = $response->getBody()->__toString();
                $escapedContent     = $this->escapeQuotes($translationContent);

                // Write the file to the system
                $this->filesystem->dumpFile($filePath, $escapedContent);
                $io->writeln(
                    '<info>'.sprintf(
                        'Translation for %1$s was downloaded successfully!',
                        $filePath
                    ).'</info>'
                );
            },
            function (ResponseException $exception) use ($promise, $io) {
                $io->writeln(
                    '<error>'.sprintf(
                        'Translation download for %1$s failed with %2$s.',
                        $promise->getFilePath(),
                        $exception->getMessage()
                    ).'</error>'
                );
            }
        );
    }

    private function escapeQuotes(string $translationContent): string
    {
        // Split line into key=" value "end
        $replaceCallback = preg_replace_callback(
            '/(^.*?=\s*")(.*".*)("\s*(;.*)?$)/m',
            static function ($match) {
                // replace unescaped " in value and recombine into full line
                $esc = preg_replace('/(?<!\\\\)"/', '\\"', $match[2]);

                return $match[1].$esc.$match[3];
            },
            $translationContent
        );

        if (null === $replaceCallback) {
            throw new \RuntimeException('RegExp failed while trying to escape quotes.');
        }

        return $replaceCallback;
    }

    private function ensureFileValid(string $filePath): void
    {
        // Initialise variables for manually parsing the file for common errors.
        $blacklist = ['YES', 'NO', 'NULL', 'FALSE', 'ON', 'OFF', 'NONE', 'TRUE'];
        $errors    = [];

        // Read the whole file at once
        // no sense streaming since we load the whole thing in production
        $file = file_get_contents($filePath);

        if (false === $file) {
            throw new InvalidFileException(sprintf('Unable to read file "%s" for checking', $filePath));
        }

        $file = explode("\n", $file);

        foreach ($file as $lineNumber => $line) {
            $realNumber = $lineNumber + 1;
            // Ignore comment lines.
            if ('' === trim($line) || ';' === $line[0]) {
                continue;
            }

            // Check that the line passes the necessary format.
            if (!preg_match('#^[A-Za-z][A-Za-z0-9_\-\.]*\s*=\s*".*"\s*(;.*)?$#', $line)) {
                $errors[] = "Line $realNumber does not match format regexp";
                continue;
            }

            // Gets the count of unescaped quotes
            preg_match_all('/(?<!\\\\)\"/', $line, $matches);

            if (2 !== count($matches[0])) {
                $errors[] = "Line $realNumber doesn't have exactly 2 unescaped quotes";
                continue;
            }

            // Check that the key is not in the blacklist.
            $key = strtoupper(trim(substr($line, 0, strpos($line, '='))));

            if (in_array($key, $blacklist)) {
                $errors[] = "Line $realNumber has blacklisted key";
            }
        }
        if (false === @parse_ini_file($filePath)) {
            $errors[] = 'Cannot load file with parse_ini_file';
        }

        if (count($errors)) {
            throw new InvalidFileException("File $filePath has following errors:\n".implode(';', $errors));
        }
    }
}

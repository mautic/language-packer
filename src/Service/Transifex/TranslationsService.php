<?php

declare(strict_types=1);

namespace App\Service\Transifex;

use App\Exception\InvalidFileException;
use App\Exception\RegexException;
use App\Service\Transifex\DTO\TranslationDTO;
use Mautic\Transifex\Connector\Translations;
use Mautic\Transifex\Exception\ResponseException;
use Mautic\Transifex\Promise;
use Mautic\Transifex\TransifexInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;

class TranslationsService
{
    public function __construct(
        private readonly TransifexInterface $transifex,
        private readonly Filesystem $filesystem,
        private readonly int $downloadMaxAttempts
    ) {
    }

    public function getTranslations(TranslationDTO $translationDTO, LoggerInterface $logger): void
    {
        $bundlePath = $translationDTO->translationsDir.'/'.$translationDTO->language.'/'.$translationDTO->bundle;
        $filePath   = $bundlePath.'/'.$translationDTO->file.'.ini';

        $this->filesystem->mkdir($bundlePath);

        // Set the timestamp on the file so our zip builds are reproducible
        $this->filesystem->touch($bundlePath, strtotime($translationDTO->lastUpdate));
        $this->filesystem->touch($filePath, strtotime($translationDTO->lastUpdate));

        for ($attempt = 1; $attempt <= $this->downloadMaxAttempts; ++$attempt) {
            try {
                $translations = $this->transifex->getConnector(Translations::class);
                $response     = $translations->download($translationDTO->slug, $translationDTO->language);
                $promise      = $this->transifex->getApiConnector()->createPromise($response);
                $promise->setFilePath($filePath);
                $this->fulfillPromises($promise, $logger);
                $this->ensureFileValid($promise->getFilePath());
                break;
            } catch (ResponseException $e) {
                if ($attempt === $this->downloadMaxAttempts) {
                    $this->outputErrors($logger, $filePath, $e->getMessage());
                    break;
                }

                sleep(2 ** $attempt);
            } catch (InvalidFileException|RegexException $e) {
                $this->outputErrors($logger, $filePath, $e->getMessage());
                break;
            }
        }
    }

    private function fulfillPromises(Promise $promise, LoggerInterface $logger): void
    {
        $promises = new \SplQueue();
        $promises->enqueue($promise);

        usleep(500000);

        $this->transifex->getApiConnector()->fulfillPromises(
            $promises,
            function (ResponseInterface $response) use (
                &$translationContent,
                $promise,
                $logger
            ) {
                $filePath           = $promise->getFilePath();
                $translationContent = $response->getBody()->__toString();
                $escapedContent     = $this->escapeQuotes($translationContent);

                // Write the file to the system
                $this->filesystem->dumpFile($filePath, $escapedContent);
                $logger->info(
                    sprintf(
                        'Translation for %1$s was downloaded successfully!',
                        $filePath
                    )
                );
            },
            function (ResponseException $e) use ($promise, $logger) {
                $logger->error(
                    sprintf(
                        'Translation download for %1$s failed with %2$s.',
                        $promise->getFilePath(),
                        $e->getMessage()
                    )
                );
                throw $e;
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
            throw new RegexException('RegExp failed while trying to escape quotes.');
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

    private function outputErrors(LoggerInterface $logger, string $filePath, string $message): void
    {
        $logger->error(sprintf('Encountered error during "%1$s" download. Error: %2$s', $filePath, $message));
    }
}

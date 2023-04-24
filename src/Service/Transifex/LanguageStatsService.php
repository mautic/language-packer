<?php

declare(strict_types=1);

namespace App\Service\Transifex;

use App\Service\Transifex\DTO\ResourceDTO;
use App\Service\Transifex\DTO\TranslationDTO;
use Mautic\Transifex\Connector\Statistics;
use Mautic\Transifex\Exception\ResponseException;
use Mautic\Transifex\TransifexInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;

class LanguageStatsService
{
    public function __construct(
        private readonly TransifexInterface $transifex,
        private readonly TranslationsService $translationsService
    ) {
    }

    public function getStatistics(ResourceDTO $resourceDTO, ConsoleLogger $logger): void
    {
        // Split the name to create our file name
        [$bundle, $file] = explode(' ', $resourceDTO->resourceName);

        if (!$bundle || !$file) {
            return;
        }

        try {
            $languageStats = $this->transifex->getConnector(Statistics::class);
            $response      = $languageStats->getLanguageStats($resourceDTO->resourceSlug, $resourceDTO->language);
            $statistics    = json_decode($response->getBody()->__toString(), true, 512, JSON_THROW_ON_ERROR);
            $languageStats = $statistics['data'] ?? [];
        } catch (ResponseException $exception) {
            $logger->error(
                sprintf(
                    'Encountered error during fetching statistics for "%1$s" resource of "%2$s" language. Error: %3$s',
                    $resourceDTO->resourceSlug,
                    $resourceDTO->language,
                    $exception->getMessage()
                )
            );

            return;
        }

        foreach ($languageStats as $languageStat) {
            $id         = $languageStat['id'];
            $idParts    = explode(':', $id);
            $language   = end($idParts);
            $attributes = $languageStat['attributes'];
            $lastUpdate = $attributes['last_update'];

            // Skip filtered languages
            if (in_array($language, $resourceDTO->filterLanguages, true)) {
                continue;
            }

            $logger->info(
                sprintf(
                    'Processing the %1$s "%2$s" resource in "%3$s" language.',
                    $bundle,
                    $file,
                    $language
                )
            );
            $translationDTO = new TranslationDTO(
                $resourceDTO->translationsDir,
                $resourceDTO->resourceSlug,
                $language,
                $bundle,
                $file,
                $lastUpdate
            );
            $this->translationsService->getTranslations($translationDTO, $logger);
        }
    }
}

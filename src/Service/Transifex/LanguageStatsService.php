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
        $resourceNameParts = explode(' ', $resourceDTO->resourceName);
        $bundle            = $resourceNameParts[0] ?? '';
        $file              = $resourceNameParts[1] ?? '';

        if (!$bundle || !$file) {
            return;
        }

        if (count($resourceDTO->languages)) {
            foreach ($resourceDTO->languages as $language) {
                $languageStats = $this->getLanguageStats($resourceDTO, $logger, $language);
                $this->processLanguageStats($resourceDTO, $logger, $languageStats, $bundle, $file);
            }
        } else {
            $languageStats = $this->getLanguageStats($resourceDTO, $logger);
            $this->processLanguageStats($resourceDTO, $logger, $languageStats, $bundle, $file);
        }
    }

    /**
     * @return array<string, string|array<string, string|int|null>>
     */
    private function getLanguageStats(ResourceDTO $resourceDTO, ConsoleLogger $logger, string $language = ''): array
    {
        $data = [];

        try {
            $languageStats = $this->transifex->getConnector(Statistics::class);
            $response      = $languageStats->getLanguageStats($resourceDTO->resourceSlug, $language);
            $statistics    = json_decode($response->getBody()->__toString(), true);
            $data          = $statistics['data'] ?? [];
        } catch (ResponseException $exception) {
            $message = sprintf(
                'Encountered error during fetching statistics for "%1$s" resource',
                $resourceDTO->resourceSlug
            );

            if ($language) {
                $message .= sprintf(' of "%1$s" language.', $language);
            }

            $message .= ' Error: '.$exception->getMessage();
            $logger->error($message);
        }

        return $data;
    }

    /**
     * @param array<string, string|array<string, string|int|null>> $languageStats
     */
    private function processLanguageStats(
        ResourceDTO $resourceDTO,
        ConsoleLogger $logger,
        array $languageStats,
        string $bundle,
        string $file
    ): void {
        foreach ($languageStats as $languageStat) {
            $idParts  = explode(':', $languageStat['id']);
            $language = end($idParts);

            if (in_array($language, $resourceDTO->skipLanguages, true)) {
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
                $languageStat['attributes']['last_update'] ?? ''
            );
            $this->translationsService->getTranslations($translationDTO, $logger);
        }
    }
}

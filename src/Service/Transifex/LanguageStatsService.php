<?php

declare(strict_types=1);

namespace App\Service\Transifex;

use App\Service\Transifex\DTO\ResourceDTO;
use App\Service\Transifex\DTO\TranslationDTO;
use Mautic\Transifex\Connector\Statistics;
use Mautic\Transifex\Exception\ResponseException;
use Mautic\Transifex\TransifexInterface;
use Psr\Log\LoggerInterface;

class LanguageStatsService
{
    public function __construct(
        private readonly TransifexInterface $transifex,
        private readonly TranslationsService $translationsService,
        private readonly int $completion
    ) {
    }

    public function getStatistics(ResourceDTO $resourceDTO, LoggerInterface $logger): void
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
     * @return array<int,array<string,mixed>>
     */
    private function getLanguageStats(ResourceDTO $resourceDTO, LoggerInterface $logger, string $language = ''): array
    {
        $data = [];

        try {
            $languageStats = $this->transifex->getConnector(Statistics::class);
            $response      = $languageStats->getLanguageStats($resourceDTO->resourceSlug, $language);
            $statistics    = json_decode((string) $response->getBody(), true);
            $data          = $statistics['data'] ?? [];
        } catch (ResponseException $e) {
            $message = sprintf(
                'Encountered error during fetching statistics for "%1$s" resource',
                $resourceDTO->resourceSlug
            );

            if ($language) {
                $message .= sprintf(' of "%1$s" language.', $language);
            }

            $message .= ' Error: '.$e->getMessage();
            $logger->error($message);
        }

        return $data;
    }

    /**
     * @param array<int,array<string,mixed>> $languageStats
     */
    private function processLanguageStats(
        ResourceDTO $resourceDTO,
        LoggerInterface $logger,
        array $languageStats,
        string $bundle,
        string $file
    ): void {
        foreach ($languageStats as $languageStat) {
            $attributes       = $languageStat['attributes'] ?? [];
            $translatedWords  = $attributes['translated_words'] ?? 0;
            $totalWords       = $attributes['total_words'] ?? 0;
            $completedPercent = $totalWords ? ($translatedWords / $totalWords) * 100 : 0;

            if ($resourceDTO->byPassCompletion || $completedPercent >= $this->completion) {
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
                    $attributes['last_update'] ?? ''
                );
                $this->translationsService->getTranslations($translationDTO, $logger);
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Service\Transifex;

use App\Service\Transifex\DTO\ResourceDTO;
use Mautic\Transifex\Connector\Resources;
use Mautic\Transifex\Exception\ResponseException;
use Mautic\Transifex\TransifexInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;

class ResourcesService
{
    public function __construct(
        private readonly TransifexInterface $transifex,
        private readonly LanguageStatsService $languageStatsService
    ) {
    }

    public function processAllResources(ResourceDTO $resourceDTO, ConsoleLogger $logger): int
    {
        try {
            $resources    = $this->transifex->getConnector(Resources::class);
            $response     = $resources->getAll();
            $body         = json_decode($response->getBody()->__toString(), true, 512, JSON_THROW_ON_ERROR);
            $resourceData = $body['data'] ?? [];
        } catch (ResponseException $exception) {
            $logger->error(
                sprintf(
                    'Encountered error during fetching all resources. Error: %1$s',
                    $exception->getMessage()
                )
            );

            return 1;
        }

        $logger->info(sprintf('Processing "%1$d" resources.', count($resourceData)));

        foreach ($resourceData as $resource) {
            $slug = $resource['attributes']['slug'] ?? '';
            $name = $resource['attributes']['name'] ?? '';

            if (!$slug || !$name) {
                continue;
            }

            $resourceDTO->resourceSlug = $slug;
            $resourceDTO->resourceName = $name;
            $this->languageStatsService->getStatistics($resourceDTO, $logger);
        }

        return 0;
    }
}

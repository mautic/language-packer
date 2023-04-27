<?php

declare(strict_types=1);

namespace App\Service\Transifex;

use App\Exception\ResourceException;
use App\Service\Transifex\DTO\ResourceDTO;
use Mautic\Transifex\Connector\Resources;
use Mautic\Transifex\Exception\ResponseException;
use Mautic\Transifex\TransifexInterface;
use Psr\Log\LoggerInterface;

class ResourcesService
{
    public function __construct(
        private readonly TransifexInterface $transifex,
        private readonly LanguageStatsService $languageStatsService
    ) {
    }

    public function processAllResources(ResourceDTO $resourceDTO, LoggerInterface $logger): void
    {
        try {
            $resources    = $this->transifex->getConnector(Resources::class);
            $response     = $resources->getAll();
            $body         = json_decode((string) $response->getBody(), true);
            $resourceData = $body['data'] ?? [];
        } catch (ResponseException $e) {
            throw new ResourceException($e->getMessage(), $e->getCode(), $e);
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
    }
}

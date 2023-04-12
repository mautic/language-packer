<?php

declare(strict_types=1);

namespace MauticLanguagePacker\Transifex\Connector;

use Mautic\Transifex\ApiConnector;
use Psr\Http\Message\ResponseInterface;

final class Languages
{
    private ApiConnector $apiConnector;

    public function __construct(ApiConnector $apiConnector)
    {
        $this->apiConnector = $apiConnector;
    }

    /**
     * @see https://developers.transifex.com/reference/get_languages-language-id
     */
    public function getLanguageDetails(string $language): ResponseInterface
    {
        $uri = $this->apiConnector->createUri('languages/'.urlencode("l:{$language}"));

        return $this->apiConnector->sendRequest($this->apiConnector->createRequest('GET', $uri));
    }
}

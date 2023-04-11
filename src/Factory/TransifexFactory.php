<?php

declare(strict_types=1);

namespace MauticLanguagePacker\Factory;

use GuzzleHttp\Client;
use Http\Factory\Guzzle\RequestFactory;
use Http\Factory\Guzzle\StreamFactory;
use Http\Factory\Guzzle\UriFactory;
use Mautic\Transifex\Config;
use Mautic\Transifex\Transifex;
use Mautic\Transifex\TransifexInterface;

class TransifexFactory
{
    public static function create(string $apiToken, string $organisation, string $project): TransifexInterface
    {
        $config = new Config();
        $config->setApiToken($apiToken);
        $config->setOrganization($organisation);
        $config->setProject($project);

        return new Transifex(new Client(), new RequestFactory(), new StreamFactory(), new UriFactory(), $config);
    }
}

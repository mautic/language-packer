<?php

declare(strict_types=1);

namespace App\Tests\Common\Client;

use Http\Factory\Guzzle\RequestFactory;
use Http\Factory\Guzzle\StreamFactory;
use Http\Factory\Guzzle\UriFactory;
use Mautic\Transifex\Config;
use Mautic\Transifex\Transifex;
use Mautic\Transifex\TransifexInterface;
use Psr\Http\Client\ClientInterface;

trait TransifexTrait
{
    private function getTransifex(ClientInterface $client): TransifexInterface
    {
        $requestFactory = new RequestFactory();
        $streamFactory  = new StreamFactory();
        $uriFactory     = new UriFactory();
        $config         = new Config();

        $config->setApiToken('some-api-token');
        $config->setOrganization('some-organization');
        $config->setProject('some-project');

        return new Transifex($client, $requestFactory, $streamFactory, $uriFactory, $config);
    }
}

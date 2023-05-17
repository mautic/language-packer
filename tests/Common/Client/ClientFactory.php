<?php

declare(strict_types=1);

namespace App\Tests\Common\Client;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;

class ClientFactory
{
    public static function create(MockHandler $handler): ClientInterface
    {
        return new Client(['handler' => HandlerStack::create($handler)]);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Common\Client;

use App\Aws\S3Config;
use Aws\MockHandler;
use Aws\S3\S3Client;
use Aws\S3\S3ClientInterface;

class S3ClientFactory
{
    public static function create(MockHandler $handler, S3Config $config): S3ClientInterface
    {
        return new S3Client([
            'version'     => $config->version,
            'region'      => $config->region,
            'credentials' => [
                'key'    => '',
                'secret' => '',
            ],
            'handler' => $handler,
        ]);
    }
}

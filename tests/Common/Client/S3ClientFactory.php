<?php

declare(strict_types=1);

namespace App\Tests\Common\Client;

use Aws\MockHandler;
use Aws\S3\S3Client;
use Aws\S3\S3ClientInterface;

class S3ClientFactory
{
    public static function create(MockHandler $handler): S3ClientInterface
    {
        return new S3Client([
            'version'     => $_ENV['AWS_S3_VERSION'],
            'region'      => $_ENV['AWS_S3_REGION'],
            'credentials' => [
                'key'    => '',
                'secret' => '',
            ],
            'handler' => $handler,
        ]);
    }
}

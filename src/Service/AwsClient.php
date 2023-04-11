<?php

declare(strict_types=1);

namespace MauticLanguagePacker\Service;

use Aws\S3\S3Client;
use Aws\Sdk;

class AwsClient
{
    private Sdk $sdk;

    public function __construct(array $arguments)
    {
        $this->sdk = new Sdk($arguments);
    }

    public function getS3Client(): S3Client
    {
        return $this->sdk->createS3();
    }
}

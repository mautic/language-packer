<?php

declare(strict_types=1);

namespace App\Service\Transifex\DTO;

class UploadPackageDTO
{
    public function __construct(public readonly string $packagesTimestampDir, public readonly string $s3Bucket)
    {
    }
}

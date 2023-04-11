<?php

declare(strict_types=1);

namespace MauticLanguagePacker\Event;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\EventDispatcher\Event;

class UploadPackageEvent extends Event
{
    public const NAME = 'mlp.upload.package';

    public function __construct(
        private readonly SymfonyStyle $io,
        private readonly string $packagesTimestampDir,
        private readonly string $s3Bucket
    ) {
    }

    public function getIo(): SymfonyStyle
    {
        return $this->io;
    }

    public function getPackagesTimestampDir(): string
    {
        return $this->packagesTimestampDir;
    }

    public function getS3Bucket(): string
    {
        return $this->s3Bucket;
    }
}

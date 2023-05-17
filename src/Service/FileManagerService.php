<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;

class FileManagerService
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly string $packagesDir,
        private readonly string $translationsDir
    ) {
    }

    public function initTranslationsDir(): string
    {
        $this->filesystem->remove($this->translationsDir);
        $this->filesystem->mkdir($this->translationsDir);

        return $this->translationsDir;
    }

    public function initPackagesDir(): string
    {
        $this->filesystem->mkdir($this->packagesDir);

        $timestamp            = (new \DateTime())->format('YmdHis');
        $packagesTimestampDir = $this->packagesDir.'/'.$timestamp;
        $this->filesystem->mkdir($packagesTimestampDir);

        return $packagesTimestampDir;
    }
}

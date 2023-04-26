<?php

declare(strict_types=1);

namespace App\Tests\Common\Trait;

trait PackagesTestFolderTrait
{
    private function backupTestPackagesFolder(): void
    {
        $packagesBckDir = $this->getPackagesBackupDir();
        $this->filesystem->remove($packagesBckDir);
        $this->filesystem->rename($this->packagesDir, $packagesBckDir);
        $this->filesystem->mkdir($this->packagesDir);
    }

    private function getPackagesBackupDir(): string
    {
        $pathParts = explode('/', $this->packagesDir);
        array_pop($pathParts);

        return implode('/', $pathParts).'/packages-bck';
    }

    private function restoreTestPackagesFolder(): void
    {
        $packagesBckDir = $this->getPackagesBackupDir();
        $this->filesystem->remove($this->packagesDir);
        $this->filesystem->rename($packagesBckDir, $this->packagesDir);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Common\Trait;

trait TranslationsTestFolderTrait
{
    private function backupTestTranslationsFolder(): void
    {
        $translationsBckDir = $this->getTranslationsBackupDir();
        $this->filesystem->remove($translationsBckDir);
        $this->filesystem->rename($this->translationsDir, $translationsBckDir);
        $this->filesystem->mkdir($this->translationsDir);
    }

    private function getTranslationsBackupDir(): string
    {
        $pathParts = explode('/', $this->translationsDir);
        array_pop($pathParts);

        return implode('/', $pathParts).'/translations-bck';
    }

    private function restoreTestTranslationsFolder(): void
    {
        $translationsBckDir = $this->getTranslationsBackupDir();
        $this->filesystem->remove($this->translationsDir);
        $this->filesystem->rename($translationsBckDir, $this->translationsDir);
    }
}

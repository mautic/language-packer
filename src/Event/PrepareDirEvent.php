<?php

declare(strict_types=1);

namespace MauticLanguagePacker\Event;

use Symfony\Contracts\EventDispatcher\Event;

class PrepareDirEvent extends Event
{
    public const NAME = 'mlp.prepare.dir';

    public function __construct(
        private readonly string $dir,
        private readonly bool $isRemoveDir = false
    ) {
    }

    public function getDir(): string
    {
        return $this->dir;
    }

    public function isRemoveDir(): bool
    {
        return $this->isRemoveDir;
    }
}

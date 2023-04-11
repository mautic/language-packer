<?php

declare(strict_types=1);

namespace MauticLanguagePacker\EventSubscriber;

use MauticLanguagePacker\Event\PrepareDirEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Filesystem;

class PrepareDirSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Filesystem $filesystem)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [PrepareDirEvent::NAME => 'makeDir'];
    }

    public function makeDir(PrepareDirEvent $event): void
    {
        $dir         = $event->getDir();
        $isRemoveDir = $event->isRemoveDir();

        if ($isRemoveDir && $this->filesystem->exists($dir)) {
            $this->filesystem->remove($dir);
        }

        $this->filesystem->mkdir($dir);
    }
}

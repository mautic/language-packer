<?php

declare(strict_types=1);

namespace MauticLanguagePacker\Tests\Functional\EventSubscriber;

use MauticLanguagePacker\Event\PrepareDirEvent;
use MauticLanguagePacker\EventSubscriber\PrepareDirSubscriber;
use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

class PrepareDirSubscriberTest extends KernelTestCase
{
    public function testGetSubscribedEvents(): void
    {
        Assert::assertSame(
            [PrepareDirEvent::NAME => 'makeDir'],
            PrepareDirSubscriber::getSubscribedEvents()
        );
    }

    public function testMakeDir(): void
    {
        $projectDir = self::getContainer()->get('parameter_bag')->get('kernel.project_dir');
        $testDir    = $projectDir.'/test-make-dir';

        $filesystem           = new Filesystem();
        $prepareDirSubscriber = new PrepareDirSubscriber($filesystem);
        $event                = new PrepareDirEvent($projectDir.'/test-make-dir');
        $prepareDirSubscriber->makeDir($event);
        Assert::assertDirectoryExists($testDir);

        $event = new PrepareDirEvent($projectDir.'/test-make-dir', true);
        $prepareDirSubscriber->makeDir($event);
        Assert::assertDirectoryExists($testDir);

        $filesystem->remove($testDir);
    }
}

<?php

declare(strict_types=1);

namespace MauticLanguagePacker\Tests\EventSubscriber;

use Aws\S3\S3Client;
use MauticLanguagePacker\Event\UploadPackageEvent;
use MauticLanguagePacker\EventSubscriber\UploadPackageSubscriber;
use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

class UploadPackageSubscriberTest extends KernelTestCase
{
    public function testGetSubscribedEvents(): void
    {
        Assert::assertSame(
            [UploadPackageEvent::NAME => 'uploadPackage'],
            UploadPackageSubscriber::getSubscribedEvents()
        );
    }

    public function testUploadPackage(): void
    {
        $s3ClientMock            = $this->createMock(S3Client::class);
        $uploadPackageSubscriber = new UploadPackageSubscriber($s3ClientMock);
        $uploadPackageEventMock  = $this->createMock(UploadPackageEvent::class);
        $symfonyStyleMock        = $this->createMock(SymfonyStyle::class);

        $projectDir           = self::getContainer()->getParameter('kernel.project_dir');
        $packagesTimestampDir = $projectDir.'/tests/Common/packages/20230419055736';
        $s3Bucket             = 'S3Bucket';

        $uploadPackageEventMock->expects(self::once())->method('getIo')->willReturn($symfonyStyleMock);
        $uploadPackageEventMock->expects(self::once())->method('getPackagesTimestampDir')->willReturn($packagesTimestampDir);
        $uploadPackageEventMock->expects(self::once())->method('getS3Bucket')->willReturn($s3Bucket);

        $s3ClientMock->expects(self::exactly(2))->method('deleteMatchingObjects')->willReturnMap(
            [
                [$s3Bucket, 'languages/af.json', '', []],
                [$s3Bucket, 'languages/af.zip', '', []],
            ]
        );

        $symfonyStyleMock->expects(self::exactly(2))->method('writeln')->willReturnMap(
            [
                [
                    '<comment>Uploaded af.json to AWS S3.</comment>',
                ],
                [
                    '<comment>Uploaded af.zip to AWS S3.</comment>',
                ],
            ]
        );

        $uploadPackageSubscriber->uploadPackage($uploadPackageEventMock);
    }
}

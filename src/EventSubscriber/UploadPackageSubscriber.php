<?php

declare(strict_types=1);

namespace MauticLanguagePacker\EventSubscriber;

use MauticLanguagePacker\Event\UploadPackageEvent;
use MauticLanguagePacker\Service\AwsClient;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Finder\Finder;

class UploadPackageSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly AwsClient $awsClient)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [UploadPackageEvent::NAME => 'uploadPackage'];
    }

    public function uploadPackage(UploadPackageEvent $event): void
    {
        $io                   = $event->getIo();
        $packagesTimestampDir = $event->getPackagesTimestampDir();
        $s3Bucket             = $event->getS3Bucket();

        // Build our S3 adapter
        $s3 = $this->awsClient->getS3Client();

        $packagesTimestampDirFinder = (new Finder())->sortByName()->depth(0)->files()->in($packagesTimestampDir);

        foreach ($packagesTimestampDirFinder as $file) {
            $fileName = $file->getFilename();
            $key      = 'languages/'.$fileName;

            // Remove our existing objects and upload fresh items
            $s3->deleteMatchingObjects($s3Bucket, $key);
            $s3->putObject(
                [
                    'Bucket'     => $s3Bucket,
                    'Key'        => $key,
                    'SourceFile' => $packagesTimestampDir.'/'.$fileName,
                    'ACL'        => 'public-read',
                ]
            );
            $io->writeln('<comment>'.sprintf('Uploaded %1$s to AWS S3.', $file->getRealPath()).'</comment>');
        }
    }
}

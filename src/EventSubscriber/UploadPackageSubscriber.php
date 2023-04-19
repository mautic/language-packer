<?php

declare(strict_types=1);

namespace MauticLanguagePacker\EventSubscriber;

use Aws\S3\S3Client;
use MauticLanguagePacker\Event\UploadPackageEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Finder\Finder;

class UploadPackageSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly S3Client $client)
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

        $packagesTimestampDirFinder = (new Finder())->sortByName()->depth(0)->files()->in($packagesTimestampDir);

        foreach ($packagesTimestampDirFinder as $file) {
            $fileName = $file->getFilename();
            $key      = 'languages/'.$fileName;

            // Remove our existing objects and upload fresh items
            $this->client->deleteMatchingObjects($s3Bucket, $key);
            $this->client->putObject(
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

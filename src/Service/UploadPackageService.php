<?php

declare(strict_types=1);

namespace App\Service;

use App\Service\Transifex\DTO\UploadPackageDTO;
use Aws\S3\S3Client;
use GuzzleHttp\Exception\ConnectException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;

class UploadPackageService
{
    public function __construct(private readonly S3Client $client, private readonly LoggerInterface $logger)
    {
    }

    public function uploadPackage(UploadPackageDTO $uploadPackageDTO): int
    {
        $packagesTimestampDirFinder = (new Finder())->sortByName()->depth(0)->files()->in(
            $uploadPackageDTO->packagesTimestampDir
        );

        $error = 0;

        foreach ($packagesTimestampDirFinder as $file) {
            $fileName = $file->getFilename();
            $key      = 'languages/'.$fileName;

            // Remove our existing objects and upload fresh items
            try {
                $this->client->deleteMatchingObjects($uploadPackageDTO->s3Bucket, $key);
            } catch (\Exception $exception) {
                $this->logger->error(
                    sprintf(
                        'Encountered error during "%1$s" deletion of previous matching objects in AWS S3. Error: %2$s',
                        $fileName,
                        $exception->getMessage()
                    )
                );
                $error = 1;
                continue;
            }

            try {
                $result = $this->client->putObject(
                    [
                        'Bucket'     => $uploadPackageDTO->s3Bucket,
                        'Key'        => $key,
                        'SourceFile' => $uploadPackageDTO->packagesTimestampDir.'/'.$fileName,
                        'ACL'        => 'public-read',
                    ]
                );
                $this->logger->info(
                    sprintf('Uploaded %1$s to AWS S3, URL: %2$s.', $file->getRealPath(), $result->get('ObjectURL'))
                );
            } catch (ConnectException $exception) {
                $this->logger->error(
                    sprintf('Encountered error during "%1$s" upload. Error: %2$s', $fileName, $exception->getMessage())
                );
                $error = 1;
                continue;
            }
        }

        return $error;
    }
}

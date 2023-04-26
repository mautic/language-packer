<?php

declare(strict_types=1);

namespace App\Service;

use App\Service\Transifex\DTO\UploadPackageDTO;
use Aws\S3\S3Client;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Finder\Finder;

class UploadPackageService
{
    public function __construct(private readonly S3Client $client)
    {
    }

    public function uploadPackage(UploadPackageDTO $uploadPackageDTO, ConsoleLogger $logger): int
    {
        $packagesTimestampDirFinder = (new Finder())
            ->sortByName()
            ->depth(0)
            ->files()
            ->in($uploadPackageDTO->packagesTimestampDir);

        $error = 0;

        foreach ($packagesTimestampDirFinder as $file) {
            $fileName = $file->getFilename();
            $key      = 'languages/'.$fileName;

            try {
                // Remove our existing objects and upload fresh items
                $this->client->deleteMatchingObjects($_ENV['AWS_S3_BUCKET'], $key);
            } catch (\Exception $exception) {
                $logger->error(
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
                $stream = fopen($uploadPackageDTO->packagesTimestampDir.'/'.$fileName, 'rb');
                $result = $this->client->putObject(
                    [
                        'Bucket' => $_ENV['AWS_S3_BUCKET'],
                        'Key'    => $key,
                        'Body'   => $stream,
                        'ACL'    => 'public-read',
                    ]
                );
                fclose($stream);
                $logger->info(
                    sprintf(
                        'Uploaded %1$s to AWS S3, URL: %2$s.',
                        $file->getRealPath(),
                        $result->get('ObjectURL')
                    )
                );
            } catch (\Exception $exception) {
                fclose($stream);
                $logger->error(
                    sprintf(
                        'Encountered error during "%1$s" upload. Error: %2$s',
                        $fileName,
                        $exception->getMessage()
                    )
                );
                $error = 1;
                continue;
            }
        }

        return $error;
    }
}

<?php

declare(strict_types=1);

namespace App\Service;

use App\Aws\S3Config;
use App\Exception\UploadPackageException;
use App\Service\Transifex\DTO\UploadPackageDTO;
use Aws\S3\S3Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;

class UploadPackageService
{
    public function __construct(private readonly S3Client $client, private readonly S3Config $config)
    {
    }

    public function uploadPackage(UploadPackageDTO $uploadPackageDTO, LoggerInterface $logger): void
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
                $this->client->deleteMatchingObjects($this->config->bucket, $key);
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

            $stream = fopen($uploadPackageDTO->packagesTimestampDir.'/'.$fileName, 'rb');

            try {
                $result = $this->client->putObject(
                    [
                        'Bucket' => $this->config->bucket,
                        'Key'    => $key,
                        'Body'   => $stream,
                        'ACL'    => 'public-read',
                    ]
                );
                $logger->info(
                    sprintf(
                        'Uploaded %1$s to AWS S3, URL: %2$s.',
                        $file->getRealPath(),
                        $result->get('ObjectURL')
                    )
                );
            } catch (\Exception $exception) {
                $logger->error(
                    sprintf(
                        'Encountered error during "%1$s" upload. Error: %2$s',
                        $fileName,
                        $exception->getMessage()
                    )
                );
                $error = 1;
            } finally {
                fclose($stream);
            }
        }

        if ($error) {
            throw new UploadPackageException('Encountered error during language packages upload to AWS S3.');
        }
    }
}

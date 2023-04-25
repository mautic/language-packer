<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service;

use App\Service\Transifex\DTO\UploadPackageDTO;
use App\Service\UploadPackageService;
use Aws\Result;
use Aws\S3\S3Client;
use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Logger\ConsoleLogger;

class UploadPackageServiceTest extends KernelTestCase
{
    public function testUploadPackage(): void
    {
        $container            = self::getContainer();
        $parameterBag         = $container->get('parameter_bag');
        $packagesTimestampDir = $parameterBag->get('mlp.packages.dir').'/20230419055736';
        $s3Bucket             = 's3bucket';

        $s3ClientMock = $this->getMockBuilder(S3Client::class)
            ->disableOriginalConstructor()
            ->addMethods(['putObject'])
            ->onlyMethods(['deleteMatchingObjects'])
            ->getMock();
        $resultMock = $this->createMock(Result::class);

        $s3ClientMock->expects(self::exactly(2))
            ->method('deleteMatchingObjects')
            ->willReturnMap([
                [Assert::equalTo($s3Bucket), Assert::equalTo('languages/af.json')],
                [Assert::equalTo($s3Bucket), Assert::equalTo('languages/af.zip')],
            ]);

        $resultMock->expects(self::exactly(2))
            ->method('get')
            ->with('ObjectURL')
            ->willReturnMap([
                ['https://some-s3-url/af.json/'],
                ['https://some-s3-url/af.zip/'],
            ]);

        $s3ClientMock->expects(self::exactly(2))
            ->method('putObject')
            ->willReturnMap([
                [Assert::equalTo([
                    'Bucket'     => $s3Bucket,
                    'Key'        => 'languages/af.json',
                    'SourceFile' => $packagesTimestampDir.'/af.json',
                    'ACL'        => 'public-read',
                ])],
                [Assert::equalTo([
                    'Bucket'     => $s3Bucket,
                    'Key'        => 'languages/af.zip',
                    'SourceFile' => $packagesTimestampDir.'/af.zip',
                    'ACL'        => 'public-read',
                ])],
            ])
            ->willReturn($resultMock);
        $uploadPackageService = new UploadPackageService($s3ClientMock);
        $loggerMock           = $this->createMock(ConsoleLogger::class);
        $uploadPackageDTO     = new UploadPackageDTO($packagesTimestampDir, $s3Bucket);
        $uploadPackageService->uploadPackage($uploadPackageDTO, $loggerMock);
    }

    public function testDeleteMatchingObjectsThrowsError(): void
    {
        $container            = self::getContainer();
        $parameterBag         = $container->get('parameter_bag');
        $packagesTimestampDir = $parameterBag->get('mlp.packages.dir').'/20230419055736';
        $s3Bucket             = 's3bucket';

        $s3ClientMock = $this->getMockBuilder(S3Client::class)
            ->disableOriginalConstructor()
            ->addMethods(['putObject'])
            ->onlyMethods(['deleteMatchingObjects'])
            ->getMock();
        $resultMock = $this->createMock(Result::class);

        $s3ClientMock->expects(self::exactly(2))
            ->method('deleteMatchingObjects')
            ->willThrowException(new \Exception('Error in deleting matching objects.'));

        $resultMock->expects(self::never())
            ->method('get')
            ->with('ObjectURL');

        $s3ClientMock->expects(self::never())
            ->method('putObject');
        $uploadPackageService = new UploadPackageService($s3ClientMock);
        $loggerMock           = $this->createMock(ConsoleLogger::class);
        $uploadPackageDTO     = new UploadPackageDTO($packagesTimestampDir, $s3Bucket);
        $error                = $uploadPackageService->uploadPackage($uploadPackageDTO, $loggerMock);
        Assert::assertSame(1, $error);
    }

    public function testPutObjectThrowsError(): void
    {
        $container            = self::getContainer();
        $parameterBag         = $container->get('parameter_bag');
        $packagesTimestampDir = $parameterBag->get('mlp.packages.dir').'/20230419055736';
        $s3Bucket             = 's3bucket';

        $s3ClientMock = $this->getMockBuilder(S3Client::class)
            ->disableOriginalConstructor()
            ->addMethods(['putObject'])
            ->onlyMethods(['deleteMatchingObjects'])
            ->getMock();
        $resultMock = $this->createMock(Result::class);

        $s3ClientMock->expects(self::exactly(2))
            ->method('deleteMatchingObjects')
            ->willReturnMap([
                [Assert::equalTo($s3Bucket), Assert::equalTo('languages/af.json')],
                [Assert::equalTo($s3Bucket), Assert::equalTo('languages/af.zip')],
            ]);

        $resultMock->expects(self::never())
            ->method('get')
            ->with('ObjectURL');

        $s3ClientMock->expects(self::exactly(2))
            ->method('putObject')
            ->willThrowException(new \Exception('Error in putObject().'));

        $uploadPackageService = new UploadPackageService($s3ClientMock);
        $loggerMock           = $this->createMock(ConsoleLogger::class);
        $uploadPackageDTO     = new UploadPackageDTO($packagesTimestampDir, $s3Bucket);
        $error                = $uploadPackageService->uploadPackage($uploadPackageDTO, $loggerMock);
        Assert::assertSame(1, $error);
    }
}

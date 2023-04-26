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
use Symfony\Component\Finder\Finder;

class UploadPackageServiceTest extends KernelTestCase
{
    private string $packagesTimestampDir;

    private string $s3Bucket;

    private array $packageLanguages = ['af', 'ar', 'cs'];

    protected function setUp(): void
    {
        $container    = self::getContainer();
        $parameterBag = $container->get('parameter_bag');
        $packagesDir  = $parameterBag->get('mlp.packages.dir');

        $finder = new Finder();
        $finder->directories()
            ->in($packagesDir)
            ->depth(0);

        if (1 !== $finder->count()) {
            $this->fail('Test package directory should not have more than 1 package to test.');
        }

        foreach ($finder as $directory) {
            $this->packagesTimestampDir = $directory->getPathname();
        }

        if (!$this->packagesTimestampDir) {
            $this->fail('Test package directory cannot be empty.');
        }

        $this->s3Bucket = $_ENV['AWS_S3_BUCKET'];
    }

    public function testUploadPackage(): void
    {
        $s3ClientMock = $this->getMockBuilder(S3Client::class)
            ->disableOriginalConstructor()
            ->addMethods(['putObject'])
            ->onlyMethods(['deleteMatchingObjects'])
            ->getMock();
        $resultMock = $this->createMock(Result::class);

        foreach ($this->packageLanguages as $language) {
            $deleteMatchingObjectsMap[] = [
                Assert::equalTo($this->s3Bucket),
                Assert::equalTo("languages/$language.json"),
            ];
            $deleteMatchingObjectsMap[] = [
                Assert::equalTo($this->s3Bucket),
                Assert::equalTo("languages/$language.zip"),
            ];
            $putObjectMap[] = [
                Assert::equalTo([
                    'Bucket'     => $this->s3Bucket,
                    'Key'        => "languages/$language.json",
                    'SourceFile' => $this->packagesTimestampDir."/$language.json",
                    'ACL'        => 'public-read',
                ]),
            ];
            $putObjectMap[] = [
                Assert::equalTo([
                    'Bucket'     => $this->s3Bucket,
                    'Key'        => "languages/$language.zip",
                    'SourceFile' => $this->packagesTimestampDir."/$language.zip",
                    'ACL'        => 'public-read',
                ]),
            ];
            $objectURLMap[] = ["https://some-s3-url/$language.json/"];
            $objectURLMap[] = ["https://some-s3-url/$language.zip/"];
        }

        $s3ClientMock->expects(self::exactly(6))
            ->method('deleteMatchingObjects')
            ->willReturnMap($deleteMatchingObjectsMap);

        $resultMock->expects(self::exactly(6))
            ->method('get')
            ->with('ObjectURL')
            ->willReturnMap($objectURLMap);

        $s3ClientMock->expects(self::exactly(6))
            ->method('putObject')
            ->willReturnMap($putObjectMap)
            ->willReturn($resultMock);

        $uploadPackageService = new UploadPackageService($s3ClientMock);
        $loggerMock           = $this->createMock(ConsoleLogger::class);
        $uploadPackageDTO     = new UploadPackageDTO($this->packagesTimestampDir);
        $uploadPackageService->uploadPackage($uploadPackageDTO, $loggerMock);
    }

    public function testDeleteMatchingObjectsThrowsError(): void
    {
        $s3ClientMock = $this->getMockBuilder(S3Client::class)
            ->disableOriginalConstructor()
            ->addMethods(['putObject'])
            ->onlyMethods(['deleteMatchingObjects'])
            ->getMock();
        $resultMock = $this->createMock(Result::class);

        $s3ClientMock->expects(self::exactly(6))
            ->method('deleteMatchingObjects')
            ->willThrowException(new \Exception('Error in deleting matching objects.'));

        $resultMock->expects(self::never())
            ->method('get')
            ->with('ObjectURL');

        $s3ClientMock->expects(self::never())
            ->method('putObject');

        $uploadPackageService = new UploadPackageService($s3ClientMock);
        $loggerMock           = $this->createMock(ConsoleLogger::class);
        $uploadPackageDTO     = new UploadPackageDTO($this->packagesTimestampDir);
        $error                = $uploadPackageService->uploadPackage($uploadPackageDTO, $loggerMock);
        Assert::assertSame(1, $error);
    }

    public function testPutObjectThrowsError(): void
    {
        $s3ClientMock = $this->getMockBuilder(S3Client::class)
            ->disableOriginalConstructor()
            ->addMethods(['putObject'])
            ->onlyMethods(['deleteMatchingObjects'])
            ->getMock();

        foreach ($this->packageLanguages as $language) {
            $deleteMatchingObjectsMap[] = [
                Assert::equalTo($this->s3Bucket),
                Assert::equalTo("languages/$language.json"),
            ];
            $deleteMatchingObjectsMap[] = [
                Assert::equalTo($this->s3Bucket),
                Assert::equalTo("languages/$language.zip"),
            ];
        }

        $s3ClientMock->expects(self::exactly(6))
            ->method('deleteMatchingObjects')
            ->willReturnMap($deleteMatchingObjectsMap);

        $s3ClientMock->expects(self::exactly(6))
            ->method('putObject')
            ->willThrowException(new \Exception('Error in putObject().'));

        $resultMock = $this->createMock(Result::class);
        $resultMock->expects(self::never())
            ->method('get')
            ->with('ObjectURL');

        $uploadPackageService = new UploadPackageService($s3ClientMock);
        $loggerMock           = $this->createMock(ConsoleLogger::class);
        $uploadPackageDTO     = new UploadPackageDTO($this->packagesTimestampDir);
        $error                = $uploadPackageService->uploadPackage($uploadPackageDTO, $loggerMock);
        Assert::assertSame(1, $error);
    }
}

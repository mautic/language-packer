<?php

declare(strict_types=1);

namespace MauticLanguagePacker\Tests\Command;

use MauticLanguagePacker\Command\MauticLanguagePackerCommand;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class MauticLanguagePackerCommandTest extends TestCase
{
    private readonly CommandTester $commandTester;

    private readonly MauticLanguagePackerCommand $command;

    protected function setUp(): void
    {
        $this->parameterBagMock    = $this->createMock(ParameterBagInterface::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);

        $application = new Application();
        $application->add(new MauticLanguagePackerCommand($this->parameterBagMock, $this->eventDispatcherMock));
        $this->command       = $application->find('mautic:language:packer');
        $this->commandTester = new CommandTester($this->command);
    }

    /**
     * @dataProvider provideInvalidTransifexCredentials
     */
    public function testEmptyTransifexCredentialsThrowsError(
        ?string $apiToken,
        ?string $organisation,
        ?string $project
    ): void {
        $this->parameterBagMock->method('get')->willReturnMap(
            [
                ['mlp.transifex.api.token', $apiToken],
                ['mlp.transifex.organisation', $organisation],
                ['mlp.transifex.project', $project],
            ]
        );

        $this->commandTester->execute(['command' => $this->command->getName()]);

        Assert::assertSame(Command::FAILURE, $this->commandTester->getStatusCode());
        $outputMessage = 'Add TRANSIFEX_API_TOKEN, TRANSIFEX_ORGANISATION and TRANSIFEX_PROJECT in .env.';
        Assert::assertStringContainsString($outputMessage, $this->getFixedCommandOutput());
    }

    public static function provideInvalidTransifexCredentials(): iterable
    {
        yield [null, null, null];
        yield ['some_api_token', null, null];
        yield [null, 'some_organisation', null];
        yield [null, null, 'some_project'];
        yield [null, 'some_organisation', 'some_project'];
        yield ['some_api_token', null, 'some_project'];
        yield ['some_api_token', 'some_organisation', null];
    }

    /**
     * @dataProvider provideInvalidAWSCredentials
     */
    public function testEmptyAWSCredentialsThrowsError(
        ?string $key,
        ?string $secret,
        ?string $region,
        ?string $bucket
    ): void {
        $this->parameterBagMock->method('get')->willReturnMap(
            [
                ['mlp.transifex.api.token', 'some_api_token'],
                ['mlp.transifex.organisation', 'some_organisation'],
                ['mlp.transifex.project', 'some_project'],
                ['mlp.packages.dir', 'some_packages_dir'],
                ['mlp.translations.dir', 'some_translations_dir'],
                ['mlp.aws.key', $key],
                ['mlp.aws.secret', $secret],
                ['mlp.aws.region', $region],
                ['mlp.aws.bucket', $bucket],
            ]
        );

        $this->commandTester->execute(['--upload-package' => true]);

        Assert::assertSame(Command::FAILURE, $this->commandTester->getStatusCode());
        $outputMessage = '[ERROR] Add AWS_KEY, AWS_SECRET, AWS_REGION and AWS_BUCKET in .env.';
        Assert::assertStringContainsString($outputMessage, $this->getFixedCommandOutput());
    }

    public static function provideInvalidAWSCredentials(): iterable
    {
        yield [null, null, null, null];

        yield ['some_key', null, null, null];
        yield [null, 'some_secret', null, null];
        yield [null, null, 'some_region', null];
        yield [null, null, null, 'some_bucket'];
        yield [null, null, 'some_region', 'some_bucket'];
        yield ['some_key', null, null, 'some_bucket'];
        yield ['some_key', 'some_secret', null, null];
        yield [null, 'some_secret', 'some_region', null];
        yield [null, 'some_secret', 'some_region', 'some_bucket'];
        yield ['some_key', null, 'some_region', 'some_bucket'];
        yield ['some_key', 'some_secret', null, 'some_bucket'];
        yield ['some_key', 'some_secret', 'some_region', null];
    }

    private function getFixedCommandOutput(): string
    {
        return preg_replace('/  +/', ' ', str_replace(PHP_EOL, '', $this->commandTester->getDisplay()));
    }
}

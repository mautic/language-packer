<?php

declare(strict_types=1);

namespace MauticLanguagePacker\Tests\Command;

use MauticLanguagePacker\Command\MauticLanguagePackerCommand;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class MauticLanguagePackerCommandTest extends TestCase
{
    private readonly CommandTester $commandTester;

    private readonly MauticLanguagePackerCommand $command;

    private ContainerInterface $container;

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

        $this->commandTester->execute(
            [
                'command' => $this->command->getName(),
            ]
        );

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

    private function getFixedCommandOutput(): string
    {
        return preg_replace('/  +/', ' ', str_replace(PHP_EOL, '', $this->commandTester->getDisplay()));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Factory\TransifexFactory;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

class TransifexFactoryTest extends TestCase
{
    public function testCreate(): void
    {
        $transifexFactory = TransifexFactory::create(
            'some_api_token',
            'some_organisation',
            'some_project'
        );

        $apiConnector = $transifexFactory->getApiConnector();
        Assert::assertSame('o:some_organisation:p:some_project', $apiConnector->buildProjectString());
    }
}

<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Command;

use AnzuSystems\AuthBundle\Tests\AbstractKernelTestCase;
use AnzuSystems\AuthBundle\Tests\Data\Fixtures\UserFixtures;
use AnzuSystems\AuthBundle\Tests\Data\Mcp\ExampleTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ListPersonalAccessTokensCommandTest extends AbstractKernelTestCase
{
    private const string COMMAND_NAME = 'anzu:personal-access-token:list';
    private const string EXPLICIT_READ_TOOLS_HEADER = 'Explicit read tools';

    public function testListShowsTheToolsOfEveryKind(): void
    {
        $this->createToken(
            userId: UserFixtures::TOKEN_MANAGER_ID,
            writeTools: [ExampleTool::WRITE],
            readTools: [ExampleTool::READ],
            explicitReadTools: [ExampleTool::EXPLICIT_READ],
        );

        $commandTester = new CommandTester(new Application(self::$kernel)->find(self::COMMAND_NAME));
        $commandTester->execute(['user-id' => UserFixtures::TOKEN_MANAGER_ID], ['interactive' => false]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $display = $commandTester->getDisplay();
        self::assertStringContainsString(self::EXPLICIT_READ_TOOLS_HEADER, $display);
        self::assertStringContainsString(self::TOKEN_NAME, $display);
        self::assertStringContainsString(ExampleTool::WRITE, $display);
        self::assertStringContainsString(ExampleTool::READ, $display);
        self::assertStringContainsString(ExampleTool::EXPLICIT_READ, $display);
    }
}

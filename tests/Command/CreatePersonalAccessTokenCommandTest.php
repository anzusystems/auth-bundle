<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Command;

use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Repository\PersonalAccessTokenRepository;
use AnzuSystems\AuthBundle\Entity\AbstractPersonalAccessToken;
use AnzuSystems\AuthBundle\Tests\AbstractKernelTestCase;
use AnzuSystems\AuthBundle\Tests\Data\Fixtures\UserFixtures;
use AnzuSystems\AuthBundle\Tests\Data\Mcp\ExampleTool;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CreatePersonalAccessTokenCommandTest extends AbstractKernelTestCase
{
    private const string COMMAND_NAME = 'anzu:personal-access-token:create';
    private const string OPTION_WRITE_TOOL = '--write-tool';
    private const string OPTION_READ_TOOL = '--read-tool';
    private const string OPTION_EXPLICIT_READ_TOOL = '--explicit-read-tool';

    /**
     * @param array<string, mixed> $options
     * @param list<string> $expectedWriteTools
     * @param list<string>|null $expectedReadTools
     * @param list<string> $expectedExplicitReadTools
     */
    #[DataProvider('createDataProvider')]
    public function testCreate(
        array $options,
        array $expectedWriteTools,
        ?array $expectedReadTools,
        array $expectedExplicitReadTools,
        string $expectedOutput,
    ): void {
        $commandTester = $this->executeCommand($options);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString($expectedOutput, $commandTester->getDisplay());
        $personalAccessTokens = $this->getStoredTokens();
        self::assertCount(1, $personalAccessTokens);
        $personalAccessToken = $personalAccessTokens->first();
        self::assertInstanceOf(AbstractPersonalAccessToken::class, $personalAccessToken);
        self::assertSame($expectedWriteTools, $personalAccessToken->getWriteTools());
        self::assertSame($expectedReadTools, $personalAccessToken->getReadTools());
        self::assertSame($expectedExplicitReadTools, $personalAccessToken->getExplicitReadTools());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<string>, list<string>|null, list<string>, string}>
     */
    public static function createDataProvider(): iterable
    {
        yield 'only a name, as run before the explicit read tools existed' => [
            [],
            [],
            null,
            [],
            'Explicit read tools: none',
        ];
        yield 'write, read and explicit read tools' => [
            [
                self::OPTION_WRITE_TOOL => [ExampleTool::WRITE],
                self::OPTION_READ_TOOL => [ExampleTool::READ],
                self::OPTION_EXPLICIT_READ_TOOL => [ExampleTool::EXPLICIT_READ],
            ],
            [ExampleTool::WRITE],
            [ExampleTool::READ],
            [ExampleTool::EXPLICIT_READ],
            'Explicit read tools: ' . ExampleTool::EXPLICIT_READ,
        ];
        yield 'every read tool together with an explicit read tool' => [
            [self::OPTION_EXPLICIT_READ_TOOL => [ExampleTool::EXPLICIT_READ]],
            [],
            null,
            [ExampleTool::EXPLICIT_READ],
            'Explicit read tools: ' . ExampleTool::EXPLICIT_READ,
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('refusedDataProvider')]
    public function testRefusedToolsCreateNoToken(array $options, string $expectedField): void
    {
        $commandTester = $this->executeCommand($options);

        self::assertSame(Command::FAILURE, $commandTester->getStatusCode());
        self::assertStringContainsString(
            sprintf('Validation failed: %s', (string) json_encode([$expectedField => [ValidationException::ERROR_FIELD_INVALID]])),
            $commandTester->getDisplay(),
        );
        self::assertCount(0, $this->getStoredTokens());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function refusedDataProvider(): iterable
    {
        yield 'an explicit read tool among the read tools' => [
            [self::OPTION_READ_TOOL => [ExampleTool::EXPLICIT_READ]],
            'readTools',
        ];
        yield 'a read tool among the explicit read tools' => [
            [self::OPTION_EXPLICIT_READ_TOOL => [ExampleTool::READ]],
            'explicitReadTools',
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    private function executeCommand(array $options): CommandTester
    {
        $commandTester = new CommandTester(new Application(self::$kernel)->find(self::COMMAND_NAME));
        $commandTester->execute(
            ['user-id' => UserFixtures::TOKEN_MANAGER_ID, '--name' => self::TOKEN_NAME] + $options,
            ['interactive' => false],
        );

        return $commandTester;
    }

    /**
     * @return Collection<int, AbstractPersonalAccessToken>
     */
    private function getStoredTokens(): Collection
    {
        $this->getEntityManager()
            ->clear();

        return self::getContainer()->get(PersonalAccessTokenRepository::class)->findByUser(
            $this->getUser(UserFixtures::TOKEN_MANAGER_ID),
        );
    }
}

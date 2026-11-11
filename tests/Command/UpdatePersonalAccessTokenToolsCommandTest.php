<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Command;

use AnzuSystems\AuthBundle\Command\CreatePersonalAccessTokenCommand;
use AnzuSystems\AuthBundle\Command\UpdatePersonalAccessTokenToolsCommand;
use AnzuSystems\AuthBundle\Entity\AbstractPersonalAccessToken;
use AnzuSystems\AuthBundle\Tests\AbstractKernelTestCase;
use AnzuSystems\AuthBundle\Tests\Data\Fixtures\UserFixtures;
use AnzuSystems\AuthBundle\Tests\Data\Mcp\ExampleTool;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class UpdatePersonalAccessTokenToolsCommandTest extends AbstractKernelTestCase
{
    private const string COMMAND_NAME = 'anzu:personal-access-token:update-tools';
    private const string OPTION_WRITE_TOOL = '--write-tool';
    private const string OPTION_READ_TOOL = '--read-tool';
    private const string OPTION_EXPLICIT_READ_TOOL = '--explicit-read-tool';
    private const string OPTION_NO_READ_TOOLS = '--no-read-tools';
    private const string STATE_REVOKED = 'revoked';
    private const string STATE_EXPIRED = 'expired';
    private const string EXPIRED_AT = '-1 year';
    private const string ANSWER_DEFAULT = '';
    private const string ANSWER_YES = 'yes';
    private const string ANSWER_NO = 'no';
    private const int NON_EXISTENT_TOKEN_ID = 999_999;

    /**
     * @param array<string, mixed> $options
     * @param list<string>|null $expectedReadTools
     * @param list<string> $expectedExplicitReadTools
     * @param list<string> $expectedWriteTools
     * @param list<string> $expectedOutputLines
     */
    #[DataProvider('updateDataProvider')]
    public function testUpdateTools(
        array $options,
        ?array $expectedReadTools,
        array $expectedExplicitReadTools,
        array $expectedWriteTools,
        array $expectedOutputLines,
    ): void {
        $personalAccessToken = $this->createToken(UserFixtures::TOKEN_MANAGER_ID, [ExampleTool::WRITE], [ExampleTool::READ]);
        $tokenId = (int) $personalAccessToken->getId();

        $commandTester = $this->executeCommand($tokenId, $options);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $display = $commandTester->getDisplay();
        self::assertStringContainsString(self::formatUpdatedOutput($tokenId, UserFixtures::TOKEN_MANAGER_ID), $display);
        foreach ($expectedOutputLines as $expectedOutputLine) {
            self::assertStringContainsString($expectedOutputLine, $display);
        }
        $this->assertStoredTools($tokenId, $expectedReadTools, $expectedExplicitReadTools, $expectedWriteTools);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<string>|null, list<string>, list<string>, list<string>}>
     */
    public static function updateDataProvider(): iterable
    {
        $none = CreatePersonalAccessTokenCommand::TOOLS_NONE;
        $everyReadTool = CreatePersonalAccessTokenCommand::READ_TOOLS_UNRESTRICTED;

        yield 'read, explicit read and write tools' => [
            [
                self::OPTION_READ_TOOL => [ExampleTool::READ],
                self::OPTION_EXPLICIT_READ_TOOL => [ExampleTool::EXPLICIT_READ],
                self::OPTION_WRITE_TOOL => [ExampleTool::WRITE],
            ],
            [ExampleTool::READ],
            [ExampleTool::EXPLICIT_READ],
            [ExampleTool::WRITE],
            [
                self::formatChange(UpdatePersonalAccessTokenToolsCommand::LABEL_READ_TOOLS, ExampleTool::READ, ExampleTool::READ),
                self::formatChange(UpdatePersonalAccessTokenToolsCommand::LABEL_EXPLICIT_READ_TOOLS, ExampleTool::EXPLICIT_READ, $none),
                self::formatChange(UpdatePersonalAccessTokenToolsCommand::LABEL_WRITE_TOOLS, ExampleTool::WRITE, ExampleTool::WRITE),
            ],
        ];
        yield 'no option' => [
            [],
            null,
            [],
            [],
            [
                self::formatChange(UpdatePersonalAccessTokenToolsCommand::LABEL_READ_TOOLS, $everyReadTool, ExampleTool::READ),
                self::formatChange(UpdatePersonalAccessTokenToolsCommand::LABEL_EXPLICIT_READ_TOOLS, $none, $none),
                self::formatChange(UpdatePersonalAccessTokenToolsCommand::LABEL_WRITE_TOOLS, $none, ExampleTool::WRITE),
            ],
        ];
        yield 'no read tools' => [
            [self::OPTION_NO_READ_TOOLS => true],
            [],
            [],
            [],
            [
                self::formatChange(UpdatePersonalAccessTokenToolsCommand::LABEL_READ_TOOLS, $none, ExampleTool::READ),
                self::formatChange(UpdatePersonalAccessTokenToolsCommand::LABEL_WRITE_TOOLS, $none, ExampleTool::WRITE),
            ],
        ];
        yield 'every read tool together with an explicit read tool' => [
            [self::OPTION_EXPLICIT_READ_TOOL => [ExampleTool::EXPLICIT_READ]],
            null,
            [ExampleTool::EXPLICIT_READ],
            [],
            [
                self::formatChange(UpdatePersonalAccessTokenToolsCommand::LABEL_READ_TOOLS, $everyReadTool, ExampleTool::READ),
                self::formatChange(UpdatePersonalAccessTokenToolsCommand::LABEL_EXPLICIT_READ_TOOLS, ExampleTool::EXPLICIT_READ, $none),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('refusedDataProvider')]
    public function testRefusedUpdateKeepsTheTools(?string $state, array $options, string $expectedOutput): void
    {
        $personalAccessToken = $this->createToken(UserFixtures::READ_TOOLS_TOKEN_MANAGER_ID, [ExampleTool::WRITE], [ExampleTool::READ]);
        $this->applyState($personalAccessToken, $state);
        $tokenId = (int) $personalAccessToken->getId();

        $commandTester = $this->executeCommand($tokenId, $options);

        self::assertSame(Command::FAILURE, $commandTester->getStatusCode());
        self::assertStringContainsString($expectedOutput, $commandTester->getDisplay());
        $this->assertStoredTools($tokenId, [ExampleTool::READ], [], [ExampleTool::WRITE]);
    }

    /**
     * @return iterable<string, array{string|null, array<string, mixed>, string}>
     */
    public static function refusedDataProvider(): iterable
    {
        yield 'read tools together with no read tools' => [
            null,
            [self::OPTION_READ_TOOL => [ExampleTool::READ], self::OPTION_NO_READ_TOOLS => true],
            'Options --read-tool and --no-read-tools are mutually exclusive.',
        ];
        yield 'a revoked token' => [
            self::STATE_REVOKED,
            [self::OPTION_EXPLICIT_READ_TOOL => [ExampleTool::EXPLICIT_READ]],
            self::formatValidationOutput('revokedAt', ValidationException::ERROR_FIELD_INVALID),
        ];
        yield 'an expired token' => [
            self::STATE_EXPIRED,
            [self::OPTION_EXPLICIT_READ_TOOL => [ExampleTool::EXPLICIT_READ]],
            self::formatValidationOutput('expiresAt', ValidationException::ERROR_FIELD_INVALID),
        ];
        yield 'a read tool the owner has no permission for' => [
            null,
            [self::OPTION_READ_TOOL => [ExampleTool::RESTRICTED_READ]],
            self::formatValidationOutput('readTools', ValidationException::ERROR_FIELD_INVALID),
        ];
        yield 'a write tool the owner has no permission for' => [
            null,
            [self::OPTION_WRITE_TOOL => [ExampleTool::RESTRICTED_WRITE]],
            self::formatValidationOutput('writeTools', ValidationException::ERROR_FIELD_INVALID),
        ];
        yield 'an unknown tool' => [
            null,
            [self::OPTION_READ_TOOL => ['example_unknown']],
            self::formatValidationOutput('readTools', ValidationException::ERROR_FIELD_INVALID),
        ];
        yield 'an explicit read tool among the read tools' => [
            null,
            [self::OPTION_READ_TOOL => [ExampleTool::EXPLICIT_READ]],
            self::formatValidationOutput('readTools', ValidationException::ERROR_FIELD_INVALID),
        ];
        yield 'a read tool among the explicit read tools' => [
            null,
            [self::OPTION_EXPLICIT_READ_TOOL => [ExampleTool::READ]],
            self::formatValidationOutput('explicitReadTools', ValidationException::ERROR_FIELD_INVALID),
        ];
    }

    public function testUpdateToolsOfANonExistentTokenFails(): void
    {
        $commandTester = $this->executeCommand(self::NON_EXISTENT_TOKEN_ID, [self::OPTION_NO_READ_TOOLS => true]);

        self::assertSame(Command::FAILURE, $commandTester->getStatusCode());
        self::assertStringContainsString(
            sprintf('Personal access token (%d) not found.', self::NON_EXISTENT_TOKEN_ID),
            $commandTester->getDisplay(),
        );
    }

    public function testOptionsSkipTheQuestionsInATerminal(): void
    {
        $personalAccessToken = $this->createToken(UserFixtures::TOKEN_MANAGER_ID, [ExampleTool::WRITE], [ExampleTool::READ]);
        $tokenId = (int) $personalAccessToken->getId();

        $commandTester = $this->executeCommand(
            tokenId: $tokenId,
            options: [self::OPTION_EXPLICIT_READ_TOOL => [ExampleTool::EXPLICIT_READ]],
            interactive: true,
        );

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringNotContainsString(UpdatePersonalAccessTokenToolsCommand::QUESTION_SAVE, $commandTester->getDisplay());
        $this->assertStoredTools($tokenId, null, [ExampleTool::EXPLICIT_READ], []);
    }

    /**
     * @param list<string> $writeTools
     * @param list<string>|null $readTools
     * @param list<string> $inputs
     * @param list<string>|null $expectedReadTools
     * @param list<string> $expectedExplicitReadTools
     * @param list<string> $expectedWriteTools
     */
    #[DataProvider('interactiveUpdateDataProvider')]
    public function testInteractiveUpdateSavesTheAnswers(
        int $ownerId,
        array $writeTools,
        ?array $readTools,
        array $inputs,
        ?array $expectedReadTools,
        array $expectedExplicitReadTools,
        array $expectedWriteTools,
        string $expectedOutput,
    ): void {
        $tokenId = (int) $this->createToken($ownerId, $writeTools, $readTools)->getId();

        $commandTester = $this->executeCommand(tokenId: $tokenId, options: [], interactive: true, inputs: $inputs);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $display = $commandTester->getDisplay();
        self::assertStringContainsString($expectedOutput, $display);
        self::assertStringContainsString(UpdatePersonalAccessTokenToolsCommand::QUESTION_SAVE, $display);
        self::assertStringContainsString(self::formatUpdatedOutput($tokenId, $ownerId), $display);
        $this->assertStoredTools($tokenId, $expectedReadTools, $expectedExplicitReadTools, $expectedWriteTools);
    }

    /**
     * @return iterable<string, array{int, list<string>, list<string>|null, list<string>, list<string>|null, list<string>, list<string>, string}>
     */
    public static function interactiveUpdateDataProvider(): iterable
    {
        $none = CreatePersonalAccessTokenCommand::TOOLS_NONE;

        yield 'tools chosen by name' => [
            UserFixtures::SUPER_ADMIN_ID,
            [ExampleTool::WRITE],
            null,
            [
                UpdatePersonalAccessTokenToolsCommand::READ_SCOPE_SELECTED,
                ExampleTool::READ . ',' . ExampleTool::RESTRICTED_READ,
                ExampleTool::EXPLICIT_READ,
                $none,
                self::ANSWER_YES,
            ],
            [ExampleTool::READ, ExampleTool::RESTRICTED_READ],
            [ExampleTool::EXPLICIT_READ],
            [],
            self::formatChange(UpdatePersonalAccessTokenToolsCommand::LABEL_WRITE_TOOLS, $none, ExampleTool::WRITE),
        ];
        yield 'tools chosen by number, the first choice being none' => [
            UserFixtures::SUPER_ADMIN_ID,
            [],
            null,
            [UpdatePersonalAccessTokenToolsCommand::READ_SCOPE_SELECTED, '2', '1', '1,2', self::ANSWER_YES],
            [ExampleTool::RESTRICTED_READ],
            [ExampleTool::EXPLICIT_READ],
            [ExampleTool::WRITE, ExampleTool::RESTRICTED_WRITE],
            self::formatChange(UpdatePersonalAccessTokenToolsCommand::LABEL_EXPLICIT_READ_TOOLS, ExampleTool::EXPLICIT_READ, $none),
        ];
        yield 'every read tool, the other answers left at the current choice' => [
            UserFixtures::TOKEN_MANAGER_ID,
            [ExampleTool::WRITE],
            [ExampleTool::READ],
            [UpdatePersonalAccessTokenToolsCommand::READ_SCOPE_ALL, self::ANSWER_DEFAULT, self::ANSWER_DEFAULT, self::ANSWER_YES],
            null,
            [],
            [ExampleTool::WRITE],
            self::formatChange(
                UpdatePersonalAccessTokenToolsCommand::LABEL_READ_TOOLS,
                CreatePersonalAccessTokenCommand::READ_TOOLS_UNRESTRICTED,
                ExampleTool::READ,
            ),
        ];
        yield 'a tool the owner may not grant is removed' => [
            UserFixtures::READ_TOOLS_TOKEN_MANAGER_ID,
            [],
            [ExampleTool::RESTRICTED_READ],
            [self::ANSWER_DEFAULT, self::ANSWER_DEFAULT, self::ANSWER_DEFAULT, self::ANSWER_YES],
            [],
            [],
            [],
            sprintf(UpdatePersonalAccessTokenToolsCommand::MESSAGE_REMOVED_WHEN_SAVED, ExampleTool::RESTRICTED_READ),
        ];
    }

    /**
     * @param list<string> $inputs
     */
    #[DataProvider('interactiveWithoutChangeDataProvider')]
    public function testInteractiveUpdateWithoutAConfirmedChangeSavesNothing(array $inputs, bool $confirmationAsked): void
    {
        $personalAccessToken = $this->createToken(
            userId: UserFixtures::TOKEN_MANAGER_ID,
            writeTools: [ExampleTool::WRITE],
            readTools: [ExampleTool::READ],
            explicitReadTools: [ExampleTool::EXPLICIT_READ],
        );
        $tokenId = (int) $personalAccessToken->getId();

        $commandTester = $this->executeCommand(tokenId: $tokenId, options: [], interactive: true, inputs: $inputs);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $display = $commandTester->getDisplay();
        self::assertStringContainsString(UpdatePersonalAccessTokenToolsCommand::MESSAGE_NOTHING_CHANGED, $display);
        self::assertSame(
            $confirmationAsked,
            str_contains($display, UpdatePersonalAccessTokenToolsCommand::QUESTION_SAVE),
        );
        $this->assertStoredTools($tokenId, [ExampleTool::READ], [ExampleTool::EXPLICIT_READ], [ExampleTool::WRITE]);
    }

    /**
     * @return iterable<string, array{list<string>, bool}>
     */
    public static function interactiveWithoutChangeDataProvider(): iterable
    {
        $none = CreatePersonalAccessTokenCommand::TOOLS_NONE;

        yield 'a change that is not confirmed' => [
            [UpdatePersonalAccessTokenToolsCommand::READ_SCOPE_NONE, $none, $none, self::ANSWER_NO],
            true,
        ];
        yield 'every answer left at the current choice' => [
            [self::ANSWER_DEFAULT, self::ANSWER_DEFAULT, self::ANSWER_DEFAULT, self::ANSWER_DEFAULT],
            false,
        ];
    }

    #[DataProvider('inactiveStateDataProvider')]
    public function testInteractiveUpdateRefusesAnInactiveTokenWithoutQuestions(string $state): void
    {
        $personalAccessToken = $this->createToken(UserFixtures::TOKEN_MANAGER_ID, [ExampleTool::WRITE], [ExampleTool::READ]);
        $this->applyState($personalAccessToken, $state);
        $tokenId = (int) $personalAccessToken->getId();

        $commandTester = $this->executeCommand(tokenId: $tokenId, options: [], interactive: true);

        self::assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $display = $commandTester->getDisplay();
        self::assertStringContainsString(
            sprintf(UpdatePersonalAccessTokenToolsCommand::MESSAGE_NOT_ACTIVE, $tokenId, self::TOKEN_NAME),
            $display,
        );
        self::assertStringNotContainsString(UpdatePersonalAccessTokenToolsCommand::LABEL_READ_TOOLS, $display);
        $this->assertStoredTools($tokenId, [ExampleTool::READ], [], [ExampleTool::WRITE]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function inactiveStateDataProvider(): iterable
    {
        yield 'a revoked token' => [self::STATE_REVOKED];
        yield 'an expired token' => [self::STATE_EXPIRED];
    }

    /**
     * @param array<string, mixed> $options
     * @param list<string> $inputs
     */
    private function executeCommand(int $tokenId, array $options, bool $interactive = false, array $inputs = []): CommandTester
    {
        $commandTester = new CommandTester(new Application(self::$kernel)->find(self::COMMAND_NAME));
        $commandTester->setInputs($inputs);
        $commandTester->execute(['id' => $tokenId] + $options, ['interactive' => $interactive]);

        return $commandTester;
    }

    /**
     * @param list<string>|null $expectedReadTools
     * @param list<string> $expectedExplicitReadTools
     * @param list<string> $expectedWriteTools
     */
    private function assertStoredTools(
        int $tokenId,
        ?array $expectedReadTools,
        array $expectedExplicitReadTools,
        array $expectedWriteTools,
    ): void {
        $storedToken = $this->getStoredToken($tokenId);
        self::assertSame($expectedReadTools, $storedToken->getReadTools());
        self::assertSame($expectedExplicitReadTools, $storedToken->getExplicitReadTools());
        self::assertSame($expectedWriteTools, $storedToken->getWriteTools());
    }

    private function applyState(AbstractPersonalAccessToken $personalAccessToken, ?string $state): void
    {
        if (self::STATE_REVOKED === $state) {
            $personalAccessToken->setRevokedAt(new DateTimeImmutable());
        }
        if (self::STATE_EXPIRED === $state) {
            $personalAccessToken->setExpiresAt(new DateTimeImmutable(self::EXPIRED_AT));
        }
        $this->getEntityManager()
            ->flush();
    }

    private static function formatUpdatedOutput(int $tokenId, int $ownerId): string
    {
        return sprintf('Updated tools of token %d "%s" of user %d.', $tokenId, self::TOKEN_NAME, $ownerId);
    }

    private static function formatChange(string $label, string $tools, string $previousTools): string
    {
        return sprintf('%s: %s (before: %s)', $label, $tools, $previousTools);
    }

    private static function formatValidationOutput(string $field, string $error): string
    {
        return sprintf('Validation failed: %s', (string) json_encode([$field => [$error]]));
    }
}

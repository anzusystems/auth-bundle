<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Domain\PersonalAccessToken\Validator\Constraints;

use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Resolver\PersonalAccessTokenToolResolver;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Validator\Constraints\PersonalAccessTokenTools;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Validator\Constraints\PersonalAccessTokenToolsValidator;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Mcp\McpToolDefinitions;
use AnzuSystems\CommonBundle\Mcp\Model\McpToolDefinition;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validation;

final class PersonalAccessTokenToolsValidatorTest extends TestCase
{
    private const string READ_TOOL = 'example_read';
    private const string EXPLICIT_READ_TOOL = 'example_explicit_read';
    private const string WRITE_TOOL = 'example_write';
    private const string PREVIEW_TOOL = 'example_preview';
    private const string UNKNOWN_TOOL = 'example_unknown';
    private const string PERMISSION = 'example_read_permission';

    /**
     * @param list<string>|null $tools
     * @param list<string> $expectedMessages
     */
    #[DataProvider('toolsDataProvider')]
    public function testOnlyConfiguredToolsOfTheRightKindAreValid(
        bool $mcpEnabled,
        PersonalAccessTokenTools $constraint,
        ?array $tools,
        array $expectedMessages,
    ): void {
        $violations = Validation::createValidatorBuilder()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
                PersonalAccessTokenToolsValidator::class => new PersonalAccessTokenToolsValidator($this->createResolver($mcpEnabled)),
            ]))
            ->getValidator()
            ->validate($tools, $constraint);

        self::assertSame(
            $expectedMessages,
            array_map(
                static fn (ConstraintViolationInterface $violation): string => (string) $violation->getMessage(),
                iterator_to_array($violations),
            ),
        );
    }

    /**
     * @return array<string, array{0: bool, 1: PersonalAccessTokenTools, 2: list<string>|null, 3: list<string>}>
     */
    public static function toolsDataProvider(): array
    {
        $invalid = [ValidationException::ERROR_FIELD_INVALID];
        $readTools = new PersonalAccessTokenTools();
        $explicitTools = new PersonalAccessTokenTools(explicitTools: true);
        $writeTools = new PersonalAccessTokenTools(writeTools: true);

        return [
            'all read tools' => [true, $readTools, null, []],
            'no read tool' => [true, $readTools, [], []],
            'read tool' => [true, $readTools, [self::READ_TOOL], []],
            'explicit read tool among the read tools' => [true, $readTools, [self::EXPLICIT_READ_TOOL], $invalid],
            'write tool among the read tools' => [true, $readTools, [self::WRITE_TOOL], $invalid],
            'preview among the read tools' => [true, $readTools, [self::PREVIEW_TOOL], $invalid],
            'unknown read tool' => [true, $readTools, [self::UNKNOWN_TOOL], $invalid],
            'read tool without mcp' => [false, $readTools, [self::READ_TOOL], $invalid],
            'no explicit read tool' => [true, $explicitTools, [], []],
            'explicit read tool' => [true, $explicitTools, [self::EXPLICIT_READ_TOOL], []],
            'read tool among the explicit read tools' => [true, $explicitTools, [self::READ_TOOL], $invalid],
            'write tool among the explicit read tools' => [true, $explicitTools, [self::WRITE_TOOL], $invalid],
            'unknown explicit read tool' => [true, $explicitTools, [self::UNKNOWN_TOOL], $invalid],
            'explicit read tool without mcp' => [false, $explicitTools, [self::EXPLICIT_READ_TOOL], $invalid],
            'no write tool' => [true, $writeTools, [], []],
            'write tool' => [true, $writeTools, [self::WRITE_TOOL], []],
            'read tool among the write tools' => [true, $writeTools, [self::READ_TOOL], $invalid],
            'explicit read tool among the write tools' => [true, $writeTools, [self::EXPLICIT_READ_TOOL], $invalid],
            'unknown write tool' => [true, $writeTools, [self::UNKNOWN_TOOL], $invalid],
            'write tool without mcp' => [false, $writeTools, [self::WRITE_TOOL], $invalid],
        ];
    }

    private function createResolver(bool $mcpEnabled): PersonalAccessTokenToolResolver
    {
        if (false === $mcpEnabled) {
            return new PersonalAccessTokenToolResolver();
        }

        $toolDefinitions = new McpToolDefinitions([
            self::READ_TOOL => new McpToolDefinition(permission: self::PERMISSION, readOnly: true),
            self::EXPLICIT_READ_TOOL => new McpToolDefinition(permission: self::PERMISSION, readOnly: true, explicitGrant: true),
            self::WRITE_TOOL => new McpToolDefinition(permission: self::PERMISSION, previewTool: self::PREVIEW_TOOL),
            self::PREVIEW_TOOL => new McpToolDefinition(readOnly: true, previewOf: self::WRITE_TOOL),
        ]);

        return new PersonalAccessTokenToolResolver(
            toolAccessChecker: new McpToolAccessChecker(toolDefinitions: $toolDefinitions, security: $this->createStub(Security::class)),
            toolDefinitions: $toolDefinitions,
        );
    }
}

<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Domain\PersonalAccessToken\Resolver;

use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Model\PersonalAccessTokenTool;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Resolver\PersonalAccessTokenToolResolver;
use AnzuSystems\CommonBundle\Mcp\McpToolDefinitions;
use AnzuSystems\CommonBundle\Mcp\Model\McpToolDefinition;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class PersonalAccessTokenToolResolverTest extends TestCase
{
    private const string READ_CATEGORY = 'exampleReads';
    private const string WRITE_CATEGORY = 'exampleWrites';
    private const string DIAGNOSTICS_CATEGORY = 'diagnostics';
    private const string READ_TOOL = 'example_read';
    private const string WRITE_TOOL = 'example_write';
    private const string PREVIEW_TOOL = 'example_preview';
    private const string NOT_PERMITTED_TOOL = 'search_app_logs';
    private const string SECOND_READ_TOOL = 'example_second_read';
    private const string EXPLICIT_GRANT_TOOL = 'example_explicit_grant';
    private const string READ_PERMISSION = 'example_read_permission';
    private const string WRITE_PERMISSION = 'example_write_permission';
    private const string EXPLICIT_GRANT_PERMISSION = 'example_update_permission';
    private const string NOT_PERMITTED_PERMISSION = 'common_appLog_read';

    /**
     * @param list<PersonalAccessTokenTool> $expectedTools
     */
    #[DataProvider('grantableToolsDataProvider')]
    public function testGrantableToolsCarryTheirCategoryAndExplicitGrantInToolDefinitionsOrderWithoutPreviews(
        bool $mcpEnabled,
        array $expectedTools,
    ): void {
        self::assertEquals($expectedTools, $this->createResolver($mcpEnabled)->resolveGrantableTools(withWriteTools: true));
    }

    /**
     * @return array<string, array{0: bool, 1: list<PersonalAccessTokenTool>}>
     */
    public static function grantableToolsDataProvider(): array
    {
        return [
            'mcp' => [
                true,
                [
                    new PersonalAccessTokenTool(name: self::READ_TOOL, writeTool: false, category: self::READ_CATEGORY, explicitGrant: false),
                    new PersonalAccessTokenTool(name: self::WRITE_TOOL, writeTool: true, category: self::WRITE_CATEGORY, explicitGrant: false),
                    new PersonalAccessTokenTool(name: self::SECOND_READ_TOOL, writeTool: false, category: self::READ_CATEGORY, explicitGrant: false),
                    new PersonalAccessTokenTool(name: self::EXPLICIT_GRANT_TOOL, writeTool: false, category: self::READ_CATEGORY, explicitGrant: true),
                ],
            ],
            'without mcp' => [false, []],
        ];
    }

    public function testGrantableToolsLeaveOutWriteToolsWhenTheUserMayNotGrantThem(): void
    {
        self::assertSame(
            [self::READ_TOOL, self::SECOND_READ_TOOL, self::EXPLICIT_GRANT_TOOL],
            array_column($this->createResolver(true)->resolveGrantableTools(withWriteTools: false), 'name'),
        );
    }

    public function testToolNamesAreSplitIntoReadAndWriteTools(): void
    {
        $resolver = $this->createResolver(true);

        self::assertSame(
            [self::READ_TOOL, self::NOT_PERMITTED_TOOL, self::SECOND_READ_TOOL, self::EXPLICIT_GRANT_TOOL],
            $resolver->resolveReadToolNames(),
        );
        self::assertSame([self::WRITE_TOOL], $resolver->resolveWriteToolNames());
    }

    private function createResolver(bool $mcpEnabled): PersonalAccessTokenToolResolver
    {
        if (false === $mcpEnabled) {
            return new PersonalAccessTokenToolResolver();
        }

        $security = $this->createStub(Security::class);
        $security->method('isGranted')
            ->willReturnCallback(
                static fn (mixed $attribute): bool => in_array(needle: $attribute, haystack: [self::READ_PERMISSION, self::WRITE_PERMISSION, self::EXPLICIT_GRANT_PERMISSION], strict: true),
            );

        $toolDefinitions = new McpToolDefinitions([
            self::READ_TOOL => new McpToolDefinition(permission: self::READ_PERMISSION, readOnly: true, category: self::READ_CATEGORY),
            self::WRITE_TOOL => new McpToolDefinition(permission: self::WRITE_PERMISSION, category: self::WRITE_CATEGORY, previewTool: self::PREVIEW_TOOL),
            self::PREVIEW_TOOL => new McpToolDefinition(readOnly: true, category: self::WRITE_CATEGORY, previewOf: self::WRITE_TOOL),
            self::NOT_PERMITTED_TOOL => new McpToolDefinition(permission: self::NOT_PERMITTED_PERMISSION, readOnly: true, category: self::DIAGNOSTICS_CATEGORY),
            self::SECOND_READ_TOOL => new McpToolDefinition(permission: self::READ_PERMISSION, readOnly: true, category: self::READ_CATEGORY),
            self::EXPLICIT_GRANT_TOOL => new McpToolDefinition(permission: self::EXPLICIT_GRANT_PERMISSION, readOnly: true, category: self::READ_CATEGORY, explicitGrant: true),
        ]);

        return new PersonalAccessTokenToolResolver(
            toolAccessChecker: new McpToolAccessChecker(toolDefinitions: $toolDefinitions, security: $security),
            toolDefinitions: $toolDefinitions,
        );
    }
}

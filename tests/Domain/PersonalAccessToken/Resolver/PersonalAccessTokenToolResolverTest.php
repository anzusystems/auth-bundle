<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Domain\PersonalAccessToken\Resolver;

use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Model\PersonalAccessTokenTool;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Resolver\PersonalAccessTokenToolResolver;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class PersonalAccessTokenToolResolverTest extends TestCase
{
    private const string ARTICLES_CATEGORY = 'articles';
    private const string QUIZZES_CATEGORY = 'quizzes';
    private const string DIAGNOSTICS_CATEGORY = 'diagnostics';
    private const string READ_TOOL = 'list_published_articles';
    private const string WRITE_TOOL = 'create_quiz';
    private const string PREVIEW_TOOL = 'preview_quiz';
    private const string NOT_PERMITTED_TOOL = 'search_app_logs';
    private const string SECOND_READ_TOOL = 'get_articles';
    private const string READ_PERMISSION = 'cms_article_read';
    private const string WRITE_PERMISSION = 'cms_quiz_create';
    private const string NOT_PERMITTED_PERMISSION = 'common_appLog_read';

    /**
     * @param list<PersonalAccessTokenTool> $expectedTools
     */
    #[DataProvider('grantableToolsDataProvider')]
    public function testGrantableToolsCarryTheirCategoryInToolPermissionsOrderWithoutPreviews(
        bool $mcpEnabled,
        array $expectedTools,
    ): void {
        self::assertEquals($expectedTools, $this->createResolver($mcpEnabled)->resolveGrantableTools());
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
                    new PersonalAccessTokenTool(name: self::READ_TOOL, writeTool: false, category: self::ARTICLES_CATEGORY),
                    new PersonalAccessTokenTool(name: self::WRITE_TOOL, writeTool: true, category: self::QUIZZES_CATEGORY),
                    new PersonalAccessTokenTool(name: self::SECOND_READ_TOOL, writeTool: false, category: self::ARTICLES_CATEGORY),
                ],
            ],
            'without mcp' => [false, []],
        ];
    }

    private function createResolver(bool $mcpEnabled): PersonalAccessTokenToolResolver
    {
        if (false === $mcpEnabled) {
            return new PersonalAccessTokenToolResolver();
        }

        $security = $this->createStub(Security::class);
        $security->method('isGranted')
            ->willReturnCallback(
                static fn (mixed $attribute): bool => in_array(needle: $attribute, haystack: [self::READ_PERMISSION, self::WRITE_PERMISSION], strict: true),
            );

        return new PersonalAccessTokenToolResolver(new McpToolAccessChecker(
            toolPermissions: [
                self::READ_TOOL => self::READ_PERMISSION,
                self::WRITE_TOOL => self::WRITE_PERMISSION,
                self::NOT_PERMITTED_TOOL => self::NOT_PERMITTED_PERMISSION,
                self::SECOND_READ_TOOL => self::READ_PERMISSION,
            ],
            security: $security,
            readOnlyToolNames: [self::READ_TOOL, self::PREVIEW_TOOL, self::NOT_PERMITTED_TOOL, self::SECOND_READ_TOOL],
            toolCategories: [
                self::READ_TOOL => self::ARTICLES_CATEGORY,
                self::WRITE_TOOL => self::QUIZZES_CATEGORY,
                self::PREVIEW_TOOL => self::QUIZZES_CATEGORY,
                self::NOT_PERMITTED_TOOL => self::DIAGNOSTICS_CATEGORY,
                self::SECOND_READ_TOOL => self::ARTICLES_CATEGORY,
            ],
            writeToolNamesByPreview: [self::PREVIEW_TOOL => self::WRITE_TOOL],
        ));
    }
}

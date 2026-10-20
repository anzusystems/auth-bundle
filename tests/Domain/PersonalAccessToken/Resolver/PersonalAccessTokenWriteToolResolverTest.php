<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Domain\PersonalAccessToken\Resolver;

use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Resolver\PersonalAccessTokenWriteToolResolver;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class PersonalAccessTokenWriteToolResolverTest extends TestCase
{
    private const string READ_TOOL = 'list_sites';
    private const string GRANTED_WRITE_TOOL = 'create_quiz';
    private const string DENIED_WRITE_TOOL = 'update_robots_txt';
    private const string GRANTED_PERMISSION = 'cms_quiz_create';
    private const string DENIED_PERMISSION = 'cms_site_update';

    public function testGrantableWriteToolsAreWriteToolsWithGrantedToolPermission(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('isGranted')
            ->willReturnCallback(static fn (mixed $attribute): bool => self::GRANTED_PERMISSION === $attribute);
        $resolver = new PersonalAccessTokenWriteToolResolver(
            new McpToolAccessChecker(
                toolPermissions: [
                    self::READ_TOOL => self::GRANTED_PERMISSION,
                    self::GRANTED_WRITE_TOOL => self::GRANTED_PERMISSION,
                    self::DENIED_WRITE_TOOL => self::DENIED_PERMISSION,
                ],
                security: $security,
                readOnlyToolNames: [self::READ_TOOL],
            ),
        );

        self::assertSame([self::GRANTED_WRITE_TOOL, self::DENIED_WRITE_TOOL], $resolver->resolveWriteTools());
        self::assertSame([self::GRANTED_WRITE_TOOL], $resolver->resolveGrantableWriteTools());
    }

    public function testWithoutMcpThereAreNoWriteTools(): void
    {
        $resolver = new PersonalAccessTokenWriteToolResolver();

        self::assertSame([], $resolver->resolveWriteTools());
        self::assertSame([], $resolver->resolveGrantableWriteTools());
    }
}

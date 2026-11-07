<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Security;

use AnzuSystems\AuthBundle\Security\Authentication\PersonalAccessTokenAuthenticator;
use AnzuSystems\AuthBundle\Security\PersonalAccessTokenToolScopeProvider;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolScope;
use AnzuSystems\Contracts\Entity\AnzuUser;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

final class PersonalAccessTokenToolScopeProviderTest extends TestCase
{
    private const string FIREWALL_NAME = 'mcp';

    public function testRequestWithoutSecurityTokenHasNoScope(): void
    {
        self::assertEquals(new McpToolScope(), new PersonalAccessTokenToolScopeProvider(new TokenStorage())->provideToolScope());
    }

    public function testTokenOfAnotherAuthenticatorHasNoScope(): void
    {
        self::assertEquals(new McpToolScope(), $this->createProvider([])->provideToolScope());
    }

    public function testScopeComesFromThePersonalAccessToken(): void
    {
        $toolScope = new McpToolScope(['example_read'], ['example_write']);

        self::assertSame(
            $toolScope,
            $this->createProvider([PersonalAccessTokenAuthenticator::TOOL_SCOPE_ATTRIBUTE => $toolScope])
                ->provideToolScope(),
        );
    }

    public function testMalformedAttributeFailsInsteadOfOpeningEveryTool(): void
    {
        $provider = $this->createProvider([PersonalAccessTokenAuthenticator::TOOL_SCOPE_ATTRIBUTE => ['example_read']]);

        $this->expectException(LogicException::class);

        $provider->provideToolScope();
    }

    /**
     * @param array<string, mixed> $tokenAttributes
     */
    private function createProvider(array $tokenAttributes): PersonalAccessTokenToolScopeProvider
    {
        $token = new PostAuthenticationToken(
            user: $this->createStub(AnzuUser::class),
            firewallName: self::FIREWALL_NAME,
            roles: [],
        );
        $token->setAttributes($tokenAttributes);
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);

        return new PersonalAccessTokenToolScopeProvider($tokenStorage);
    }
}

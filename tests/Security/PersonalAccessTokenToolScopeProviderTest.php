<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Security;

use AnzuSystems\AuthBundle\Security\Authentication\PersonalAccessTokenAuthenticator;
use AnzuSystems\AuthBundle\Security\PersonalAccessTokenToolScopeProvider;
use AnzuSystems\Contracts\Entity\AnzuUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

final class PersonalAccessTokenToolScopeProviderTest extends TestCase
{
    private const string FIREWALL_NAME = 'mcp';
    private const string TOOL = 'list_published_articles';

    /**
     * @param array<string, mixed>|null $tokenAttributes
     * @param list<string>|null $expectedTools
     */
    #[DataProvider('scopeDataProvider')]
    public function testScopeComesFromThePersonalAccessTokenAttributes(?array $tokenAttributes, ?array $expectedTools): void
    {
        $tokenStorage = new TokenStorage();
        if (is_array($tokenAttributes)) {
            $token = new PostAuthenticationToken(
                user: $this->createStub(AnzuUser::class),
                firewallName: self::FIREWALL_NAME,
                roles: [],
            );
            $token->setAttributes($tokenAttributes);
            $tokenStorage->setToken($token);
        }

        self::assertSame($expectedTools, new PersonalAccessTokenToolScopeProvider($tokenStorage)->provideAllowedToolNames());
    }

    /**
     * @return array<string, array{0: array<string, mixed>|null, 1: list<string>|null}>
     */
    public static function scopeDataProvider(): array
    {
        return [
            'no security token' => [null, null],
            'token of another authenticator' => [[], null],
            'personal access token without narrowing' => [[PersonalAccessTokenAuthenticator::TOOLS_ATTRIBUTE => null], null],
            'personal access token with narrowing' => [[PersonalAccessTokenAuthenticator::TOOLS_ATTRIBUTE => [self::TOOL]], [self::TOOL]],
        ];
    }
}

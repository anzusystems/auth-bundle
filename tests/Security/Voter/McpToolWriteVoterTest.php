<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Security\Voter;

use AnzuSystems\AuthBundle\Model\Enum\PersonalAccessTokenScope;
use AnzuSystems\AuthBundle\Security\Authentication\PersonalAccessTokenAuthenticator;
use AnzuSystems\AuthBundle\Security\Voter\McpToolWriteVoter;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;
use AnzuSystems\Contracts\Entity\AnzuUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

final class McpToolWriteVoterTest extends TestCase
{
    private const string FIREWALL_NAME = 'mcp';

    /**
     * @param list<string> $roles
     */
    #[DataProvider('writeVoteDataProvider')]
    public function testWriteVoteDependsOnlyOnTokenScope(?PersonalAccessTokenScope $scope, array $roles, int $expectedVote): void
    {
        $result = new McpToolWriteVoter()->vote(
            token: $this->createToken(scope: $scope, roles: $roles),
            subject: null,
            attributes: [McpToolAccessChecker::WRITE_PERMISSION],
        );

        self::assertSame($expectedVote, $result);
    }

    public function testUnsupportedAttributeAbstains(): void
    {
        $result = new McpToolWriteVoter()->vote(
            token: $this->createToken(scope: PersonalAccessTokenScope::ReadWrite, roles: []),
            subject: null,
            attributes: ['some_other_permission'],
        );

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    /**
     * @return array<string, array{0: PersonalAccessTokenScope|null, 1: list<string>, 2: int}>
     */
    public static function writeVoteDataProvider(): array
    {
        return [
            'read-write scope' => [PersonalAccessTokenScope::ReadWrite, [], VoterInterface::ACCESS_GRANTED],
            'super admin with read scope' => [PersonalAccessTokenScope::Read, [AnzuUser::ROLE_SUPER_ADMIN], VoterInterface::ACCESS_DENIED],
            'super admin token without scope' => [null, [AnzuUser::ROLE_SUPER_ADMIN], VoterInterface::ACCESS_DENIED],
        ];
    }

    /**
     * @param list<string> $roles
     */
    private function createToken(?PersonalAccessTokenScope $scope, array $roles): PostAuthenticationToken
    {
        $token = new PostAuthenticationToken(
            user: $this->createStub(AnzuUser::class),
            firewallName: self::FIREWALL_NAME,
            roles: $roles,
        );
        if ($scope instanceof PersonalAccessTokenScope) {
            $token->setAttribute(PersonalAccessTokenAuthenticator::SCOPE_ATTRIBUTE, $scope);
        }

        return $token;
    }
}

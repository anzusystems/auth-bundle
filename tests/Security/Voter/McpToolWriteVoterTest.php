<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Security\Voter;

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
    private const string GRANTED_TOOL = 'create_quiz';
    private const string OTHER_TOOL = 'update_robots_txt';

    /**
     * @param list<string>|null $writeTools
     * @param list<string> $roles
     */
    #[DataProvider('writeVoteDataProvider')]
    public function testWriteVoteDependsOnlyOnTheToolsTheTokenGrants(?array $writeTools, array $roles, int $expectedVote): void
    {
        $result = new McpToolWriteVoter()->vote(
            token: $this->createToken(writeTools: $writeTools, roles: $roles),
            subject: self::GRANTED_TOOL,
            attributes: [McpToolAccessChecker::WRITE_PERMISSION],
        );

        self::assertSame($expectedVote, $result);
    }

    #[DataProvider('unsupportedVoteDataProvider')]
    public function testUnsupportedVoteAbstains(string $attribute, ?string $subject): void
    {
        $result = new McpToolWriteVoter()->vote(
            token: $this->createToken(writeTools: [self::GRANTED_TOOL], roles: []),
            subject: $subject,
            attributes: [$attribute],
        );

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    /**
     * @return array<string, array{0: list<string>|null, 1: list<string>, 2: int}>
     */
    public static function writeVoteDataProvider(): array
    {
        return [
            'token granting the tool' => [[self::GRANTED_TOOL], [], VoterInterface::ACCESS_GRANTED],
            'super admin token granting another tool' => [[self::OTHER_TOOL], [AnzuUser::ROLE_SUPER_ADMIN], VoterInterface::ACCESS_DENIED],
            'super admin token without write tools' => [null, [AnzuUser::ROLE_SUPER_ADMIN], VoterInterface::ACCESS_DENIED],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string|null}>
     */
    public static function unsupportedVoteDataProvider(): array
    {
        return [
            'other attribute' => ['some_other_permission', self::GRANTED_TOOL],
            'write attribute without a tool name' => [McpToolAccessChecker::WRITE_PERMISSION, null],
        ];
    }

    /**
     * @param list<string>|null $writeTools
     * @param list<string> $roles
     */
    private function createToken(?array $writeTools, array $roles): PostAuthenticationToken
    {
        $token = new PostAuthenticationToken(
            user: $this->createStub(AnzuUser::class),
            firewallName: self::FIREWALL_NAME,
            roles: $roles,
        );
        if (is_array($writeTools)) {
            $token->setAttribute(PersonalAccessTokenAuthenticator::WRITE_TOOLS_ATTRIBUTE, $writeTools);
        }

        return $token;
    }
}

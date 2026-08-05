<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Security\Voter;

use AnzuSystems\AuthBundle\Security\PersonalAccessTokenPermission;
use AnzuSystems\AuthBundle\Security\Voter\PersonalAccessTokenVoter;
use AnzuSystems\AuthBundle\Tests\Data\Entity\PersonalAccessToken;
use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\Contracts\Security\Grant;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class PersonalAccessTokenVoterTest extends TestCase
{
    public function testCreateIsDeniedWithoutMcpRole(): void
    {
        $voter = $this->createVoter(isGrantedMap: [
            [AnzuUser::ROLE_SUPER_ADMIN, null, false],
            [PersonalAccessTokenPermission::ROLE_MCP, null, false],
        ]);
        $user = $this->createUser(permissions: [
            PersonalAccessTokenPermission::PERSONAL_ACCESS_TOKEN_CREATE => Grant::ALLOW,
        ]);

        $result = $voter->vote($this->createToken($user), null, [PersonalAccessTokenPermission::PERSONAL_ACCESS_TOKEN_CREATE]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testCreateIsGrantedWithMcpRoleAndAllowGrant(): void
    {
        $voter = $this->createVoter(isGrantedMap: [
            [AnzuUser::ROLE_SUPER_ADMIN, null, false],
            [PersonalAccessTokenPermission::ROLE_MCP, null, true],
        ]);
        $user = $this->createUser(permissions: [
            PersonalAccessTokenPermission::PERSONAL_ACCESS_TOKEN_CREATE => Grant::ALLOW,
        ]);

        $result = $voter->vote($this->createToken($user), null, [PersonalAccessTokenPermission::PERSONAL_ACCESS_TOKEN_CREATE]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testForeignTokenIsDeniedEvenWithAllowGrant(): void
    {
        $voter = $this->createVoter(isGrantedMap: [
            [AnzuUser::ROLE_SUPER_ADMIN, null, false],
        ]);
        $user = $this->createUser(permissions: [
            PersonalAccessTokenPermission::PERSONAL_ACCESS_TOKEN_REVOKE => Grant::ALLOW,
        ]);
        $foreignOwner = $this->createConfiguredMock(AnzuUser::class, ['is' => false]);
        $subject = new PersonalAccessToken()
            ->setUser($foreignOwner);

        $result = $voter->vote($this->createToken($user), $subject, [PersonalAccessTokenPermission::PERSONAL_ACCESS_TOKEN_REVOKE]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testOwnTokenIsGrantedWithAllowOwnerGrant(): void
    {
        $voter = $this->createVoter(isGrantedMap: [
            [AnzuUser::ROLE_SUPER_ADMIN, null, false],
        ]);
        $user = $this->createUser(permissions: [
            PersonalAccessTokenPermission::PERSONAL_ACCESS_TOKEN_REVOKE => Grant::ALLOW_OWNER,
        ]);
        $owner = $this->createConfiguredMock(AnzuUser::class, ['is' => true]);
        $subject = new PersonalAccessToken()
            ->setUser($owner);

        $result = $voter->vote($this->createToken($user), $subject, [PersonalAccessTokenPermission::PERSONAL_ACCESS_TOKEN_REVOKE]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testSuperAdminBypassesPermissionCheck(): void
    {
        $voter = $this->createVoter(isGrantedMap: [
            [AnzuUser::ROLE_SUPER_ADMIN, null, true],
        ]);
        $user = $this->createUser(permissions: []);

        $result = $voter->vote($this->createToken($user), null, [PersonalAccessTokenPermission::PERSONAL_ACCESS_TOKEN_READ]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testUnsupportedAttributeAbstains(): void
    {
        $voter = $this->createVoter(isGrantedMap: []);

        $result = $voter->vote($this->createToken($this->createUser([])), null, ['some_other_permission']);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    /**
     * @param list<array{0: string, 1: mixed, 2: bool}> $isGrantedMap
     */
    private function createVoter(array $isGrantedMap): PersonalAccessTokenVoter
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')
            ->willReturnMap($isGrantedMap);
        $voter = new PersonalAccessTokenVoter();
        $voter->setSecurity($security);

        return $voter;
    }

    /**
     * @param array<string, int> $permissions
     */
    private function createUser(array $permissions): AnzuUser
    {
        return $this->createConfiguredMock(AnzuUser::class, ['getResolvedPermissions' => $permissions]);
    }

    private function createToken(AnzuUser $user): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')
            ->willReturn($user);

        return $token;
    }
}

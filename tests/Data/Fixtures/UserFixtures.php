<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Data\Fixtures;

use AnzuSystems\AuthBundle\Security\PersonalAccessTokenPermission;
use AnzuSystems\AuthBundle\Tests\Data\Entity\User;
use AnzuSystems\AuthBundle\Tests\Data\Mcp\ExampleTool;
use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Security\Grant;
use Generator;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * @extends AbstractFixtures<User>
 */
final class UserFixtures extends AbstractFixtures
{
    public const int TOKEN_MANAGER_ID = 10;
    public const int READ_TOOLS_TOKEN_MANAGER_ID = 11;
    public const int WITHOUT_CREATE_ROLE_ID = 12;
    public const int WITHOUT_PERMISSIONS_ID = 13;
    public const int DISABLED_ID = 14;

    public const string TOKEN_MANAGER_EMAIL = 'token.manager@example.com';
    public const string DISABLED_EMAIL = 'disabled@example.com';
    public const string PASSWORD = 'example-password';

    private const array TOKEN_PERMISSIONS = [
        PersonalAccessTokenPermission::PERSONAL_ACCESS_TOKEN_CREATE => Grant::ALLOW,
        PersonalAccessTokenPermission::PERSONAL_ACCESS_TOKEN_READ => Grant::ALLOW,
        PersonalAccessTokenPermission::PERSONAL_ACCESS_TOKEN_REVOKE => Grant::ALLOW_OWNER,
        ExampleTool::READ => Grant::ALLOW,
        ExampleTool::EXPLICIT_READ => Grant::ALLOW,
        ExampleTool::WRITE => Grant::ALLOW,
    ];
    private const array WRITE_TOOLS_PERMISSION = [
        PersonalAccessTokenPermission::PERSONAL_ACCESS_TOKEN_CREATE_WITH_WRITE_TOOLS => Grant::ALLOW,
    ];
    private const array CREATE_ROLES = [User::ROLE_USER, PersonalAccessTokenPermission::ROLE_MCP];

    public static function getIndexKey(): string
    {
        return User::class;
    }

    public function getEnvironments(): array
    {
        return ['test'];
    }

    public function load(ProgressBar $progressBar): void
    {
        foreach ($progressBar->iterate($this->getData()) as $user) {
            $this->entityManager->persist($user);
        }
        $this->entityManager->flush();
    }

    /**
     * @return Generator<int, User>
     */
    private function getData(): Generator
    {
        $consoleUser = $this->createUser(AnzuApp::getUserIdConsole(), 'console@example.com');
        $consoleUser
            ->setCreatedBy($consoleUser)
            ->setModifiedBy($consoleUser)
        ;

        yield $consoleUser;

        $users = [
            $this->createUser(AnzuApp::getUserIdAnonymous(), 'anonymous@example.com'),
            $this->createUser(self::TOKEN_MANAGER_ID, self::TOKEN_MANAGER_EMAIL)
                ->setRoles(self::CREATE_ROLES)
                ->setPermissions(self::TOKEN_PERMISSIONS + self::WRITE_TOOLS_PERMISSION),
            $this->createUser(self::READ_TOOLS_TOKEN_MANAGER_ID, 'read.tools.token.manager@example.com')
                ->setRoles(self::CREATE_ROLES)
                ->setPermissions(self::TOKEN_PERMISSIONS),
            $this->createUser(self::WITHOUT_CREATE_ROLE_ID, 'without.create.role@example.com')
                ->setPermissions(self::TOKEN_PERMISSIONS + self::WRITE_TOOLS_PERMISSION),
            $this->createUser(self::WITHOUT_PERMISSIONS_ID, 'without.permissions@example.com')
                ->setRoles(self::CREATE_ROLES),
            $this->createUser(self::DISABLED_ID, self::DISABLED_EMAIL)
                ->setRoles(self::CREATE_ROLES)
                ->setPermissions(self::TOKEN_PERMISSIONS + self::WRITE_TOOLS_PERMISSION)
                ->setEnabled(false),
        ];
        foreach ($users as $user) {
            yield $user
                ->setCreatedBy($consoleUser)
                ->setModifiedBy($consoleUser)
            ;
        }
    }

    private function createUser(int $id, string $email): User
    {
        return new User()
            ->setId($id)
            ->setEmail($email)
            ->setPassword(self::PASSWORD)
            ->setRoles([User::ROLE_USER])
            ->setCreatedAt(AnzuApp::getAppDate())
            ->setModifiedAt(AnzuApp::getAppDate())
        ;
    }
}

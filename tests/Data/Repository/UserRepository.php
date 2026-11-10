<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Data\Repository;

use AnzuSystems\AuthBundle\Contracts\OAuth2AuthUserRepositoryInterface;
use AnzuSystems\AuthBundle\Tests\Data\Entity\User;
use AnzuSystems\CommonBundle\Repository\AbstractAnzuRepository;

/**
 * @extends AbstractAnzuRepository<User>
 */
final class UserRepository extends AbstractAnzuRepository implements OAuth2AuthUserRepositoryInterface
{
    public function findOneBySsoUserId(string $ssoUserId): ?User
    {
        return $this->find((int) $ssoUserId);
    }

    public function findOneBySsoEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    protected function getEntityClass(): string
    {
        return User::class;
    }
}

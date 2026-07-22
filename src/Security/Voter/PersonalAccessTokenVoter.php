<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Security\Voter;

use AnzuSystems\AuthBundle\Entity\AbstractPersonalAccessToken;
use AnzuSystems\AuthBundle\Security\PersonalAccessTokenPermission;
use AnzuSystems\CommonBundle\Security\Voter\AbstractVoter;
use AnzuSystems\Contracts\Entity\AnzuUser;
use InvalidArgumentException;

/**
 * @template-extends AbstractVoter<string, AbstractPersonalAccessToken|null>
 */
final class PersonalAccessTokenVoter extends AbstractVoter
{
    public function __construct(
        private readonly string $createRole = PersonalAccessTokenPermission::ROLE_MCP,
    ) {
    }

    protected function businessLogicVote(string $attribute, mixed $subject, AnzuUser $user): ?bool
    {
        if (PersonalAccessTokenPermission::PERSONAL_ACCESS_TOKEN_CREATE === $attribute
            && false === $this->security->isGranted($this->createRole)
        ) {
            return false;
        }

        if ($subject instanceof AbstractPersonalAccessToken && false === $subject->getUser()->is($user)) {
            return false;
        }

        return null;
    }

    protected function resolveAllowOwner(mixed $subject, AnzuUser $user): bool
    {
        if (null === $subject) {
            return true;
        }
        if ($subject instanceof AbstractPersonalAccessToken) {
            return $subject->getUser()
                ->is($user);
        }

        throw new InvalidArgumentException('Unsupported value for ' . self::class . '::' . __FUNCTION__);
    }

    protected function getSupportedPermissions(): array
    {
        return [
            PersonalAccessTokenPermission::PERSONAL_ACCESS_TOKEN_CREATE,
            PersonalAccessTokenPermission::PERSONAL_ACCESS_TOKEN_READ,
            PersonalAccessTokenPermission::PERSONAL_ACCESS_TOKEN_REVOKE,
        ];
    }
}

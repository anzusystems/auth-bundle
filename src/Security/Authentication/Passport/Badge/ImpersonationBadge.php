<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Security\Authentication\Passport\Badge;

use AnzuSystems\AuthBundle\Security\Http\EventListener\UserProviderListener;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\AuthenticationServiceException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\BadgeInterface;

final class ImpersonationBadge implements BadgeInterface
{
    /**
     * @var callable|null
     */
    private $userLoader;
    private ?UserInterface $originalUser = null;

    public function __construct(
        private string $userIdentifier,
        ?callable $userLoader = null,
    ) {
        $this->userLoader = $userLoader;
    }

    /**
     * @throws AuthenticationException when the user cannot be found
     */
    public function getOriginalUser(): UserInterface
    {
        if ($this->originalUser instanceof UserInterface) {
            return $this->originalUser;
        }

        if (null === $this->userLoader) {
            throw new \LogicException(\sprintf('No user loader is configured, did you forget to register the "%s" listener?', UserProviderListener::class));
        }

        $user = ($this->userLoader)($this->userIdentifier);

        // No user has been found via the $this->userLoader callback
        if (null === $user) {
            $exception = new UserNotFoundException();
            $exception->setUserIdentifier($this->userIdentifier);

            throw $exception;
        }

        if (!$user instanceof UserInterface) {
            throw new AuthenticationServiceException(
                \sprintf('The user provider must return a UserInterface object, "%s" given.', get_debug_type($user))
            );
        }

        return $this->originalUser = $user;
    }

    public function getUserLoader(): ?callable
    {
        return $this->userLoader;
    }

    public function setUserLoader(callable $userLoader): void
    {
        $this->userLoader = $userLoader;
    }

    public function isResolved(): bool
    {
        return true;
    }
}

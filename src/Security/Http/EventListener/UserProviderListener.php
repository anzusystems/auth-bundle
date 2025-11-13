<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Security\Http\EventListener;

use AnzuSystems\AuthBundle\Security\Authentication\Passport\Badge\ImpersonationBadge;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

final class UserProviderListener
{
    public function __construct(
        private UserProviderInterface $userProvider,
    ) {
    }

    public function checkPassport(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();
        if (!$passport->hasBadge(ImpersonationBadge::class)) {
            return;
        }

        /** @var ImpersonationBadge $badge */
        $badge = $passport->getBadge(ImpersonationBadge::class);
        if (null !== $badge->getUserLoader()) {
            return;
        }

        $badge->setUserLoader($this->userProvider->loadUserByIdentifier(...));
    }
}

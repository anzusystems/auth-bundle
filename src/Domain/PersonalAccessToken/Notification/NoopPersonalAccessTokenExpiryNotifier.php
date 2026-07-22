<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Notification;

use AnzuSystems\AuthBundle\Contracts\PersonalAccessTokenExpiryNotifierInterface;
use AnzuSystems\AuthBundle\Entity\AbstractPersonalAccessToken;

final readonly class NoopPersonalAccessTokenExpiryNotifier implements PersonalAccessTokenExpiryNotifierInterface
{
    public function notifyExpiring(AbstractPersonalAccessToken $personalAccessToken, int $daysRemaining): bool
    {
        return false;
    }
}

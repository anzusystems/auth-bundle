<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Contracts;

use AnzuSystems\AuthBundle\Entity\AbstractPersonalAccessToken;

interface PersonalAccessTokenExpiryNotifierInterface
{
    /**
     * Notification windows of consecutive daily runs may overlap, the implementation
     * must be idempotent per ($personalAccessToken, $daysRemaining) pair.
     */
    public function notifyExpiring(AbstractPersonalAccessToken $personalAccessToken, int $daysRemaining): bool;
}

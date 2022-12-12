<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Contracts;

interface OAuth2AuthUserRepositoryInterface
{
    public function findOneBySsoUserId(string $ssoUserId): ?AnzuAuthUserInterface;
}

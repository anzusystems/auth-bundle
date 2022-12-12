<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Contracts;

use AnzuSystems\AuthBundle\Model\RefreshTokenDto;

interface RefreshTokenStorageInterface
{
    public function isValid(string $userId, string $deviceId, string $tokenHashPlain): bool;

    public function store(RefreshTokenDto $refreshTokenDto): void;

    public function invalidate(string $userId, string $deviceId): void;
}

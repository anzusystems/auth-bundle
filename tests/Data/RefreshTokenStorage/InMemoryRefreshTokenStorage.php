<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Data\RefreshTokenStorage;

use AnzuSystems\AuthBundle\Contracts\RefreshTokenStorageInterface;
use AnzuSystems\AuthBundle\Model\RefreshTokenDto;

final class InMemoryRefreshTokenStorage implements RefreshTokenStorageInterface
{
    /**
     * @var array<string, string>
     */
    private array $tokenHashes = [];

    public function isValid(string $userId, string $deviceId, string $tokenHashPlain): bool
    {
        return $tokenHashPlain === ($this->tokenHashes[$this->createKey($userId, $deviceId)] ?? null);
    }

    public function store(RefreshTokenDto $refreshTokenDto): void
    {
        $key = $this->createKey($refreshTokenDto->getUserId(), $refreshTokenDto->getDevice()->getDeviceId());
        $this->tokenHashes[$key] = $refreshTokenDto->getTokenHashPlain();
    }

    public function invalidate(string $userId, string $deviceId): void
    {
        unset($this->tokenHashes[$this->createKey($userId, $deviceId)]);
    }

    private function createKey(string $userId, string $deviceId): string
    {
        return $userId . ':' . $deviceId;
    }
}

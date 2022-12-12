<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\RefreshTokenStorage;

use AnzuSystems\AuthBundle\Contracts\RefreshTokenStorageInterface;
use AnzuSystems\AuthBundle\Model\RefreshTokenDto;
use AnzuSystems\CommonBundle\Traits\SerializerAwareTrait;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Redis;
use RedisException;

final class RedisRefreshTokenStorage implements RefreshTokenStorageInterface
{
    use SerializerAwareTrait;

    public function __construct(
        private readonly Redis $storageRedis,
    ) {
    }

    /**
     * @throws RedisException
     * @throws SerializerException
     */
    public function isValid(string $userId, string $deviceId, string $tokenHashPlain): bool
    {
        $refreshToken = $this->storageRedis->get(
            $this->getUserDeviceKey($userId, $deviceId)
        );
        if (empty($refreshToken)) {
            return false;
        }
        $refreshTokenDto = $this->serializer->deserialize($refreshToken, RefreshTokenDto::class);

        return $refreshTokenDto->getTokenHashPlain() === $tokenHashPlain;
    }

    /**
     * @throws SerializerException
     * @throws RedisException
     */
    public function store(RefreshTokenDto $refreshTokenDto): void
    {
        $this->storageRedis->setex(
            $this->getUserDeviceKey($refreshTokenDto->getUserId(), $refreshTokenDto->getDevice()->getDeviceId()),
            $refreshTokenDto->getExpiresAt()->getTimestamp() - time(),
            $this->serializer->serialize($refreshTokenDto),
        );
    }

    /**
     * @throws RedisException
     */
    public function invalidate(string $userId, string $deviceId): void
    {
        $this->storageRedis->del(
            $this->getUserDeviceKey($userId, $deviceId)
        );
    }

    private function getUserDeviceKey(string $userId, string $deviceId): string
    {
        return sprintf('refresh_token:%s:%s', $userId, $deviceId);
    }
}

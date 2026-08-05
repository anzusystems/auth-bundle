<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Cache;

use AnzuSystems\Contracts\AnzuApp;
use DateTimeImmutable;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final readonly class PersonalAccessTokenAuthCache
{
    public const int USER_ID_EXPIRE_TIME = 300;

    private const string CACHE_KEY_PREFIX = 'pat_auth_user_id';
    private const string VERSION_KEY_PREFIX = 'pat_auth_version';
    private const int VERSION_INITIAL = 1;
    private const int VERSION_INCREMENT = 1;
    private const int VERSION_EXPIRE_TIME = 2 * self::USER_ID_EXPIRE_TIME;

    public function __construct(
        private CacheItemPoolInterface $patAuthCache,
    ) {
    }

    public function getInvalidationVersion(string $tokenHash): int
    {
        return $this->readVersion($this->patAuthCache->getItem($this->getVersionKey($tokenHash)));
    }

    public function getUserId(string $tokenHash, int $version): ?int
    {
        $cacheItem = $this->patAuthCache->getItem($this->getCacheKey($tokenHash, $version));
        if (false === $cacheItem->isHit()) {
            return null;
        }
        $userId = $cacheItem->get();
        if (is_int($userId)) {
            return $userId;
        }

        return null;
    }

    public function storeUserId(string $tokenHash, int $version, int $userId, DateTimeImmutable $expiresAt): void
    {
        $secondsToExpiry = $expiresAt->getTimestamp() - AnzuApp::date()->getTimestamp();
        if ($secondsToExpiry <= 0) {
            return;
        }
        $cacheItem = $this->patAuthCache->getItem($this->getCacheKey($tokenHash, $version));
        $cacheItem->set($userId);
        $cacheItem->expiresAfter(min(self::USER_ID_EXPIRE_TIME, $secondsToExpiry));
        $this->patAuthCache->save($cacheItem);
    }

    public function invalidate(string $tokenHash): void
    {
        $cacheItem = $this->patAuthCache->getItem($this->getVersionKey($tokenHash));
        $cacheItem->set($this->readVersion($cacheItem) + self::VERSION_INCREMENT);
        $cacheItem->expiresAfter(self::VERSION_EXPIRE_TIME);
        $this->patAuthCache->save($cacheItem);
    }

    private function readVersion(CacheItemInterface $cacheItem): int
    {
        if (false === $cacheItem->isHit()) {
            return self::VERSION_INITIAL;
        }
        $version = $cacheItem->get();
        if (is_int($version)) {
            return $version;
        }

        return self::VERSION_INITIAL;
    }

    private function getCacheKey(string $tokenHash, int $version): string
    {
        return sprintf('%s_%d_%s', self::CACHE_KEY_PREFIX, $version, $tokenHash);
    }

    private function getVersionKey(string $tokenHash): string
    {
        return self::VERSION_KEY_PREFIX . '_' . $tokenHash;
    }
}

<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Cache;

use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Model\CachedPersonalAccessToken;
use AnzuSystems\Contracts\AnzuApp;
use DateTimeImmutable;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final readonly class PersonalAccessTokenAuthCache
{
    public const int USER_ID_EXPIRE_TIME = 300;

    private const string CACHE_KEY_PREFIX = 'pat_auth_token';
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

    public function getToken(string $tokenHash, int $version): ?CachedPersonalAccessToken
    {
        $cacheItem = $this->patAuthCache->getItem($this->getCacheKey($tokenHash, $version));
        if (false === $cacheItem->isHit()) {
            return null;
        }
        $token = $cacheItem->get();
        if ($token instanceof CachedPersonalAccessToken) {
            return $token;
        }

        return null;
    }

    public function storeToken(
        string $tokenHash,
        int $version,
        CachedPersonalAccessToken $token,
        ?DateTimeImmutable $expiresAt,
    ): void {
        $expireTime = $this->resolveExpireTime($expiresAt);
        if ($expireTime <= 0) {
            return;
        }
        $cacheItem = $this->patAuthCache->getItem($this->getCacheKey($tokenHash, $version));
        $cacheItem->set($token);
        $cacheItem->expiresAfter($expireTime);
        $this->patAuthCache->save($cacheItem);
    }

    public function invalidate(string $tokenHash): void
    {
        $cacheItem = $this->patAuthCache->getItem($this->getVersionKey($tokenHash));
        $cacheItem->set($this->readVersion($cacheItem) + self::VERSION_INCREMENT);
        $cacheItem->expiresAfter(self::VERSION_EXPIRE_TIME);
        $this->patAuthCache->save($cacheItem);
    }

    private function resolveExpireTime(?DateTimeImmutable $expiresAt): int
    {
        if (null === $expiresAt) {
            return self::USER_ID_EXPIRE_TIME;
        }

        return min(self::USER_ID_EXPIRE_TIME, $expiresAt->getTimestamp() - AnzuApp::date()->getTimestamp());
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

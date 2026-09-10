<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Domain\PersonalAccessToken\Cache;

use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Cache\PersonalAccessTokenAuthCache;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Model\CachedPersonalAccessToken;
use AnzuSystems\Contracts\AnzuApp;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class PersonalAccessTokenAuthCacheTest extends TestCase
{
    private const string TOKEN_HASH = 'pat_auth_cache_test_token_hash';
    private const int PERSONAL_ACCESS_TOKEN_ID = 7;
    private const int USER_ID = 42;
    private const string FUTURE_EXPIRY = '+1 hour';

    private PersonalAccessTokenAuthCache $cache;

    public static function setUpBeforeClass(): void
    {
        AnzuApp::init(
            appNamespace: 'anzusystems',
            appSystem: 'authbundle',
            appVersion: 'test',
            appReadOnlyMode: false,
            projectDir: sys_get_temp_dir(),
            appEnv: 'test',
        );
    }

    protected function setUp(): void
    {
        $this->cache = new PersonalAccessTokenAuthCache(new ArrayAdapter());
    }

    public function testGetTokenReturnsNullOnMiss(): void
    {
        self::assertNull($this->cache->getToken(self::TOKEN_HASH, $this->cache->getInvalidationVersion(self::TOKEN_HASH)));
    }

    public function testStoredTokenWithFutureExpiryIsReturned(): void
    {
        $version = $this->cache->getInvalidationVersion(self::TOKEN_HASH);
        $this->cache->storeToken(self::TOKEN_HASH, $version, $this->createToken(), AnzuApp::date(self::FUTURE_EXPIRY));

        $cached = $this->cache->getToken(self::TOKEN_HASH, $version);

        self::assertNotNull($cached);
        self::assertSame(self::PERSONAL_ACCESS_TOKEN_ID, $cached->personalAccessTokenId);
        self::assertSame(self::USER_ID, $cached->userId);
    }

    public function testNeverExpiringTokenIsStored(): void
    {
        $version = $this->cache->getInvalidationVersion(self::TOKEN_HASH);
        $this->cache->storeToken(self::TOKEN_HASH, $version, $this->createToken(), null);

        self::assertNotNull($this->cache->getToken(self::TOKEN_HASH, $version));
    }

    public function testExpiredTokenIsNeverStored(): void
    {
        $version = $this->cache->getInvalidationVersion(self::TOKEN_HASH);
        $this->cache->storeToken(self::TOKEN_HASH, $version, $this->createToken(), AnzuApp::date('-1 second'));

        self::assertNull($this->cache->getToken(self::TOKEN_HASH, $version));
    }

    public function testInvalidateRejectsCachedToken(): void
    {
        $version = $this->cache->getInvalidationVersion(self::TOKEN_HASH);
        $this->cache->storeToken(self::TOKEN_HASH, $version, $this->createToken(), AnzuApp::date(self::FUTURE_EXPIRY));
        $this->cache->invalidate(self::TOKEN_HASH);

        self::assertNull($this->cache->getToken(self::TOKEN_HASH, $this->cache->getInvalidationVersion(self::TOKEN_HASH)));
    }

    public function testStaleWriteWithPreInvalidationVersionIsUnreadable(): void
    {
        $staleVersion = $this->cache->getInvalidationVersion(self::TOKEN_HASH);
        $this->cache->invalidate(self::TOKEN_HASH);
        $this->cache->storeToken(self::TOKEN_HASH, $staleVersion, $this->createToken(), AnzuApp::date(self::FUTURE_EXPIRY));

        self::assertNull($this->cache->getToken(self::TOKEN_HASH, $this->cache->getInvalidationVersion(self::TOKEN_HASH)));
    }

    private function createToken(): CachedPersonalAccessToken
    {
        return new CachedPersonalAccessToken(self::PERSONAL_ACCESS_TOKEN_ID, self::USER_ID);
    }
}

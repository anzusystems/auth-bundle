<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Entity;

use AnzuSystems\AuthBundle\Entity\AbstractPersonalAccessToken;
use AnzuSystems\AuthBundle\Tests\Data\Entity\PersonalAccessToken;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AbstractPersonalAccessTokenTest extends TestCase
{
    public function testHashTokenProducesDeterministicSha256(): void
    {
        $plainToken = AbstractPersonalAccessToken::TOKEN_PREFIX . str_repeat('a', 64);
        $hash = AbstractPersonalAccessToken::hashToken($plainToken);

        self::assertSame(AbstractPersonalAccessToken::TOKEN_HASH_LENGTH, strlen($hash));
        self::assertSame(hash('sha256', $plainToken), $hash);
        self::assertSame($hash, AbstractPersonalAccessToken::hashToken($plainToken));
    }

    public function testNewTokenIsNotRevokedAndHasDefaultExpiry(): void
    {
        $personalAccessToken = new PersonalAccessToken();

        self::assertFalse($personalAccessToken->isRevoked());
        self::assertGreaterThan(new DateTimeImmutable('+1 month'), $personalAccessToken->getExpiresAt());
        self::assertLessThanOrEqual(new DateTimeImmutable(AbstractPersonalAccessToken::MAX_EXPIRES_AT_DATE), $personalAccessToken->getExpiresAt());
    }

    public function testRevokedAtMarksTokenAsRevoked(): void
    {
        $personalAccessToken = new PersonalAccessToken();
        $personalAccessToken->setRevokedAt(new DateTimeImmutable());

        self::assertTrue($personalAccessToken->isRevoked());
    }
}

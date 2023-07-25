<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Util;

use AnzuSystems\AuthBundle\Configuration\JwtConfiguration;
use AnzuSystems\AuthBundle\Exception\MissingConfigurationException;
use AnzuSystems\AuthBundle\Util\JwtUtil;
use DateTimeImmutable;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Token\RegisteredClaims;
use PHPUnit\Framework\TestCase;

final class JwtUtilTest extends TestCase
{
    private JwtUtil $jwtUtil;
    private JwtConfiguration $jwtConfiguration;

    protected function setUp(): void
    {
        $this->jwtConfiguration = new JwtConfiguration(
            audience: 'anz',
            algorithm: 'ES256',
            publicCert: base64_decode('LS0tLS1CRUdJTiBQVUJMSUMgS0VZLS0tLS0KTUZrd0V3WUhLb1pJemowQ0FRWUlLb1pJemowREFRY0RRZ0FFT0hIQzMvVDZ3cnVNTk40OTBqVE1maXNFa1BoTQp5eFNiQm1DK0hSYWF2Z1dLM25aNG1HNFlmVDRxMmF1L3V4TktBTjJvODJOdW84VTQ0ZXZkcExkQUhBPT0KLS0tLS1FTkQgUFVCTElDIEtFWS0tLS0tCg==', true),
            privateCert: base64_decode('LS0tLS1CRUdJTiBFQyBQUklWQVRFIEtFWS0tLS0tCk1IY0NBUUVFSU9OdDBIdEdzUGdRRytKY2VGUk5GdlRZMVVVeDVITTdqQzNVS1ZHRHBlS0tvQW9HQ0NxR1NNNDkKQXdFSG9VUURRZ0FFT0hIQzMvVDZ3cnVNTk40OTBqVE1maXNFa1BoTXl4U2JCbUMrSFJhYXZnV0szblo0bUc0WQpmVDRxMmF1L3V4TktBTjJvODJOdW84VTQ0ZXZkcExkQUhBPT0KLS0tLS1FTkQgRUMgUFJJVkFURSBLRVktLS0tLQo=', true),
            lifetime: 3_600
        );
        $this->jwtUtil = new JwtUtil($this->jwtConfiguration);
    }

    /**
     * @throws MissingConfigurationException
     */
    public function testCreate(): void
    {
        $expireAt = new DateTimeImmutable(sprintf('+%d seconds', $this->jwtConfiguration->getLifetime()));
        $jwt = $this->jwtUtil->create('123');
        $this->assertInstanceOf(Plain::class, $jwt);
        $this->assertSame('123', $jwt->claims()->get(RegisteredClaims::SUBJECT));
        $this->assertSame(
            $expireAt->getTimestamp(),
            $jwt->claims()->get(RegisteredClaims::EXPIRATION_TIME)->getTimestamp()
        );
        $this->assertSame(['anz'], $jwt->claims()->get(RegisteredClaims::AUDIENCE));
    }

    /**
     * @throws MissingConfigurationException
     */
    public function testCreateWithClaims(): void
    {
        $expireAt = new DateTimeImmutable(sprintf('+%d seconds', $this->jwtConfiguration->getLifetime()));
        $jwt = $this->jwtUtil->create('123', $expireAt, ['foo' => 'bar', 'qux' => 'quux']);
        $this->assertInstanceOf(Plain::class, $jwt);
        $this->assertSame('123', $jwt->claims()->get(RegisteredClaims::SUBJECT));
        $this->assertSame('bar', $jwt->claims()->get('foo'));
        $this->assertSame('quux', $jwt->claims()->get('quxx'));
        $this->assertSame(
            $expireAt->getTimestamp(),
            $jwt->claims()->get(RegisteredClaims::EXPIRATION_TIME)->getTimestamp()
        );
        $this->assertSame(['anz'], $jwt->claims()->get(RegisteredClaims::AUDIENCE));
    }
}

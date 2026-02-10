<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Util;

use AnzuSystems\AuthBundle\Configuration\CookieConfiguration;
use AnzuSystems\AuthBundle\Configuration\JwtConfiguration;
use AnzuSystems\AuthBundle\Util\HttpUtil;
use AnzuSystems\AuthBundle\Util\JwtUtil;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

final class HttpUtilTest extends TestCase
{
    private HttpUtil $httpUtil;
    private JwtConfiguration $jwtConfiguration;
    private JwtUtil $jwtUtil;

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

        $cookieConfiguration = new CookieConfiguration(
            domain: '.example.com',
            sameSite: 'strict',
            secure: true,
            jwtPayloadCookieName: 'qux',
            jwtSignatureCookieName: 'quux',
            deviceIdCookieName: 'corge',
            refreshTokenCookieName: 'garply',
            refreshTokenExistenceCookieName: 'grault',
            refreshTokenLifetime: 3_600,
        );

        $this->httpUtil = new HttpUtil(
            cookieConfiguration: $cookieConfiguration,
            jwtConfiguration: $this->jwtConfiguration,
            authRedirectDefaultUrl: 'https://example.com',
            authRedirectQueryUrlAllowedPattern: '^https?://(.*)\.example\.com$'
        );
    }

    public function testStoreJwtOnResponse(): void
    {
        $response = new Response();
        $token = $this->jwtUtil->create('123');
        $expireAt = new DateTimeImmutable(sprintf('+%d seconds', $this->jwtConfiguration->getLifetime()));
        $this->httpUtil->storeJwtOnResponse($response, $token, $expireAt);

        $cookies = $response->headers->getCookies();
        $this->assertCount(2, $cookies);

        /** @var Cookie $payloadCookie */
        $payloadCookie = current(
            array_filter($cookies, static function (Cookie $cookie) {
                return 'qux' === $cookie->getName();
            })
        );

        /** @var Cookie $signCookie */
        $signCookie = current(
            array_filter($cookies, static function (Cookie $cookie) {
                return 'quux' === $cookie->getName();
            })
        );

        $this->assertSame($token->toString(), $payloadCookie->getValue() . '.' . $signCookie->getValue());
        $this->assertTrue($payloadCookie->isSecure());
        $this->assertFalse($payloadCookie->isHttpOnly());
        $this->assertSame('strict', $payloadCookie->getSameSite());
        $this->assertSame('.example.com', $payloadCookie->getDomain());

        $this->assertTrue($signCookie->isSecure());
        $this->assertTrue($signCookie->isHttpOnly());
        $this->assertSame('strict', $payloadCookie->getSameSite());
        $this->assertSame('.example.com', $signCookie->getDomain());
    }
}

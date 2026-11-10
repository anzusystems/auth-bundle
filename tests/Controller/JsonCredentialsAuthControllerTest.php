<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Controller;

use AnzuSystems\AuthBundle\Configuration\CookieConfiguration;
use AnzuSystems\AuthBundle\Model\RefreshTokenDto;
use AnzuSystems\AuthBundle\Tests\Data\Fixtures\UserFixtures;
use AnzuSystems\AuthBundle\Tests\Data\RefreshTokenStorage\InMemoryRefreshTokenStorage;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class JsonCredentialsAuthControllerTest extends AbstractControllerTestCase
{
    private const string LOGIN_URI = '/auth/login';
    private const string REFRESH_TOKEN_URI = '/auth/refresh-token';
    private const string LOGOUT_URI = '/auth/logout';
    private const string DEFAULT_REDIRECT_URL = 'http://localhost/default-redirect';
    private const string ALLOWED_REDIRECT_URL = 'http://localhost/allowed/page';

    public function testLogin(): void
    {
        $response = $this->login();

        self::assertResponseIsSuccessful();
        $jwt = new Parser(new JoseEncoder())->parse($response['access_token']);
        self::assertTrue($jwt->isRelatedTo((string) UserFixtures::TOKEN_MANAGER_ID));
        self::assertSame($response['refresh_token'], $this->getCookieValue($this->getCookieConfiguration()->getRefreshTokenCookieName()));
        self::assertNotNull($this->getCookieValue($this->getCookieConfiguration()->getJwtPayloadCookieName()));
        self::assertNotNull($this->getCookieValue($this->getCookieConfiguration()->getJwtSignatureCookieName()));
        self::assertNotNull($this->getCookieValue($this->getCookieConfiguration()->getRefreshTokenExistenceCookieName()));
        self::assertTrue($this->isRefreshTokenValid($response['refresh_token']));
    }

    public function testLoginGrantsAccessToTheApi(): void
    {
        $this->login();

        $this->requestJson(Request::METHOD_GET, '/personal-access-token');

        self::assertResponseIsSuccessful();
    }

    #[DataProvider('badCredentialsDataProvider')]
    public function testLoginWithBadCredentialsIsRejected(string $username, string $password): void
    {
        $response = $this->requestJson(Request::METHOD_POST, self::LOGIN_URI, [
            'username' => $username,
            'password' => $password,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertSame('bad_credentials', $response['error']);
        self::assertNull($this->getCookieValue($this->getCookieConfiguration()->getRefreshTokenCookieName()));
        self::assertNull($this->getCookieValue($this->getCookieConfiguration()->getJwtPayloadCookieName()));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badCredentialsDataProvider(): iterable
    {
        yield 'a wrong password' => [UserFixtures::TOKEN_MANAGER_EMAIL, 'wrong-password'];
        yield 'an unknown user' => ['unknown@example.com', UserFixtures::PASSWORD];
    }

    #[DataProvider('refreshTokenMethodDataProvider')]
    public function testRefreshToken(string $method): void
    {
        $loginResponse = $this->login();

        $response = $this->requestJson($method, self::REFRESH_TOKEN_URI);

        self::assertResponseIsSuccessful();
        self::assertNotSame($loginResponse['refresh_token'], $response['refresh_token']);
        self::assertSame($response['refresh_token'], $this->getCookieValue($this->getCookieConfiguration()->getRefreshTokenCookieName()));
        self::assertTrue($this->isRefreshTokenValid($response['refresh_token']));
        self::assertFalse($this->isRefreshTokenValid($loginResponse['refresh_token']));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refreshTokenMethodDataProvider(): iterable
    {
        yield 'POST' => [Request::METHOD_POST];
        yield 'GET' => [Request::METHOD_GET];
    }

    public function testRefreshTokenWithoutARefreshTokenIsUnauthorized(): void
    {
        $response = $this->requestJson(Request::METHOD_POST, self::REFRESH_TOKEN_URI);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertSame('unauthorized', $response['message']);
    }

    public function testRefreshTokenUnknownToTheStorageIsRejected(): void
    {
        self::$client->getCookieJar()->set(new Cookie(
            $this->getCookieConfiguration()->getRefreshTokenCookieName(),
            UserFixtures::TOKEN_MANAGER_ID . RefreshTokenDto::REFRESH_TOKEN_SEPARATOR . 'unknown-token',
        ));

        $response = $this->requestJson(Request::METHOD_POST, self::REFRESH_TOKEN_URI);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertSame('unable_to_refresh', $response['message']);
        self::assertNull($this->getCookieValue($this->getCookieConfiguration()->getJwtPayloadCookieName()));
    }

    public function testLogout(): void
    {
        $loginResponse = $this->login();

        self::$client->request(Request::METHOD_GET, self::LOGOUT_URI);

        self::assertResponseRedirects(self::DEFAULT_REDIRECT_URL);
        self::assertFalse($this->isRefreshTokenValid($loginResponse['refresh_token']));
        self::assertNull($this->getCookieValue($this->getCookieConfiguration()->getRefreshTokenCookieName()));
        self::assertNull($this->getCookieValue($this->getCookieConfiguration()->getRefreshTokenExistenceCookieName()));
        self::assertNull($this->getCookieValue($this->getCookieConfiguration()->getJwtPayloadCookieName()));
        self::assertNull($this->getCookieValue($this->getCookieConfiguration()->getJwtSignatureCookieName()));
    }

    #[DataProvider('logoutRedirectDataProvider')]
    public function testLogoutRedirectsOnlyToAnAllowedUrl(string $url, string $expectedRedirectUrl): void
    {
        self::$client->request(Request::METHOD_GET, self::LOGOUT_URI, ['url' => $url]);

        self::assertResponseRedirects($expectedRedirectUrl);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function logoutRedirectDataProvider(): iterable
    {
        yield 'an allowed url' => [self::ALLOWED_REDIRECT_URL, self::ALLOWED_REDIRECT_URL];
        yield 'a url outside the allowed pattern' => ['http://other.example.com/allowed/page', self::DEFAULT_REDIRECT_URL];
    }

    /**
     * @return array<int|string, mixed>
     */
    private function login(): array
    {
        return $this->requestJson(Request::METHOD_POST, self::LOGIN_URI, [
            'username' => UserFixtures::TOKEN_MANAGER_EMAIL,
            'password' => UserFixtures::PASSWORD,
        ]);
    }

    private function isRefreshTokenValid(string $refreshToken): bool
    {
        [$userId, $tokenHash] = explode(RefreshTokenDto::REFRESH_TOKEN_SEPARATOR, $refreshToken, 2);

        return self::getContainer()->get(InMemoryRefreshTokenStorage::class)->isValid(
            userId: $userId,
            deviceId: (string) $this->getCookieValue($this->getCookieConfiguration()->getDeviceIdCookieName()),
            tokenHashPlain: $tokenHash,
        );
    }

    private function getCookieValue(string $name): ?string
    {
        return self::$client->getCookieJar()->get($name)?->getValue();
    }

    private function getCookieConfiguration(): CookieConfiguration
    {
        return self::getContainer()->get(CookieConfiguration::class);
    }
}

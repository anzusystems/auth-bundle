<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Controller;

use AnzuSystems\AuthBundle\Configuration\CookieConfiguration;
use AnzuSystems\AuthBundle\Domain\Process\OAuth2\GrantAccessByOAuth2TokenProcess;
use AnzuSystems\AuthBundle\Model\Enum\UserOAuthLoginState;
use AnzuSystems\AuthBundle\Tests\AnzuTestKernel;
use AnzuSystems\AuthBundle\Tests\Data\Fixtures\UserFixtures;
use AnzuSystems\AuthBundle\Util\JwtUtil;
use AnzuSystems\CommonBundle\Kernel\AnzuKernel;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class OAuth2AuthControllerTest extends AbstractControllerTestCase
{
    private const string LOGIN_URI = '/auth/login';
    private const string AUTHORIZE_URI = '/auth/authorize';
    private const string PROVIDER_AUTHORIZE_URL = 'https://sso.example.com/authorize';
    private const string PROVIDER_TOKEN_URL = 'https://sso.example.com/token';
    private const string DEFAULT_REDIRECT_URL = 'http://localhost/default-redirect';
    private const string CODE = 'example-code';
    private const int NON_EXISTENT_USER_ID = 999;

    public function testLogin(): void
    {
        self::$client->request(Request::METHOD_GET, self::LOGIN_URI);

        self::assertResponseStatusCodeSame(Response::HTTP_FOUND);
        $location = (string) self::$client->getResponse()->headers->get('Location');
        self::assertStringStartsWith(self::PROVIDER_AUTHORIZE_URL . '?', $location);
        $query = $this->parseQuery($location);
        self::assertSame('example-audience', $query['client_id']);
        self::assertSame('code', $query['response_type']);
        self::assertSame('http://localhost/auth/authorize', $query['redirect_uri']);
        self::assertSame('openid email', $query['scope']);
        self::assertNotEmpty($query['state']);
    }

    public function testAuthorize(): void
    {
        $state = $this->requestState();
        $tokenResponse = $this->mockTokenResponse(UserFixtures::TOKEN_MANAGER_ID);

        self::$client->request(Request::METHOD_GET, self::AUTHORIZE_URI, [
            'code' => self::CODE,
            'state' => $state,
        ]);

        self::assertSame(UserOAuthLoginState::Success->toString(), $this->getRedirectLoginState());
        self::assertSame(self::PROVIDER_TOKEN_URL, $tokenResponse->getRequestUrl());
        self::assertStringContainsString('code=' . self::CODE, $tokenResponse->getRequestOptions()['body']);
        self::assertStringStartsWith(UserFixtures::TOKEN_MANAGER_ID . ':', (string) $this->getRefreshTokenCookieValue());

        $this->requestJson(Request::METHOD_GET, '/personal-access-token');

        self::assertResponseIsSuccessful();
    }

    #[DataProvider('authorizeFailureDataProvider')]
    public function testAuthorizeFailure(int $userId, string $tokenExpiresAt, int $providerStatusCode, UserOAuthLoginState $expectedLoginState): void
    {
        $state = $this->requestState();
        $this->mockTokenResponse(userId: $userId, tokenExpiresAt: $tokenExpiresAt, statusCode: $providerStatusCode);

        self::$client->request(Request::METHOD_GET, self::AUTHORIZE_URI, [
            'code' => self::CODE,
            'state' => $state,
        ]);

        self::assertSame($expectedLoginState->toString(), $this->getRedirectLoginState());
        self::assertNull($this->getRefreshTokenCookieValue());
    }

    /**
     * @return iterable<string, array{int, string, int, UserOAuthLoginState}>
     */
    public static function authorizeFailureDataProvider(): iterable
    {
        yield 'the provider fails' => [
            UserFixtures::TOKEN_MANAGER_ID,
            '+1 hour',
            Response::HTTP_INTERNAL_SERVER_ERROR,
            UserOAuthLoginState::FailureSsoCommunicationFailed,
        ];
        yield 'an expired access token' => [
            UserFixtures::TOKEN_MANAGER_ID,
            '-1 hour',
            Response::HTTP_OK,
            UserOAuthLoginState::FailureUnauthorized,
        ];
        yield 'an unknown user' => [
            self::NON_EXISTENT_USER_ID,
            '+1 hour',
            Response::HTTP_OK,
            UserOAuthLoginState::FailureUnauthorized,
        ];
        yield 'a disabled user' => [
            UserFixtures::DISABLED_ID,
            '+1 hour',
            Response::HTTP_OK,
            UserOAuthLoginState::FailureUnauthorized,
        ];
    }

    #[DataProvider('invalidStateDataProvider')]
    public function testAuthorizeWithAnInvalidStateIsForbidden(?string $state): void
    {
        $this->requestState();
        $this->mockTokenResponse(UserFixtures::TOKEN_MANAGER_ID);

        self::$client->request(Request::METHOD_GET, self::AUTHORIZE_URI, array_filter(['code' => self::CODE, 'state' => $state]));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame(0, self::getContainer()->get(MockHttpClient::class)->getRequestsCount());
        self::assertNull($this->getRefreshTokenCookieValue());
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function invalidStateDataProvider(): iterable
    {
        yield 'no state' => [null];
        yield 'a state the application did not issue' => ['invalid-state'];
    }

    protected static function createKernel(array $options = []): AnzuKernel
    {
        return parent::createKernel(['environment' => AnzuTestKernel::ENVIRONMENT_OAUTH2] + $options);
    }

    private function requestState(): string
    {
        self::$client->request(Request::METHOD_GET, self::LOGIN_URI);

        return $this->parseQuery((string) self::$client->getResponse()->headers->get('Location'))['state'];
    }

    private function mockTokenResponse(int $userId, string $tokenExpiresAt = '+1 hour', int $statusCode = Response::HTTP_OK): MockResponse
    {
        $accessToken = self::getContainer()->get(JwtUtil::class)->create((string) $userId, new DateTimeImmutable($tokenExpiresAt));
        $response = new MockResponse(
            json_encode(['access_token' => $accessToken->toString()], JSON_THROW_ON_ERROR),
            ['http_code' => $statusCode],
        );
        self::getContainer()->get(MockHttpClient::class)->setResponseFactory($response);

        return $response;
    }

    private function getRedirectLoginState(): string
    {
        self::assertResponseStatusCodeSame(Response::HTTP_FOUND);
        $location = (string) self::$client->getResponse()->headers->get('Location');
        self::assertStringStartsWith(self::DEFAULT_REDIRECT_URL . '?', $location);

        return $this->parseQuery($location)[GrantAccessByOAuth2TokenProcess::LOGIN_STATE_QUERY_PARAM];
    }

    private function getRefreshTokenCookieValue(): ?string
    {
        $cookieName = self::getContainer()->get(CookieConfiguration::class)->getRefreshTokenCookieName();

        return self::$client->getCookieJar()->get($cookieName)?->getValue();
    }

    /**
     * @return array<string, string>
     */
    private function parseQuery(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }
}

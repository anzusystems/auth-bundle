<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Util;

use AnzuSystems\AuthBundle\Configuration\CookieConfiguration;
use AnzuSystems\AuthBundle\Configuration\JwtConfiguration;
use AnzuSystems\AuthBundle\Exception\InvalidJwtException;
use AnzuSystems\AuthBundle\Exception\InvalidRefreshTokenException;
use AnzuSystems\AuthBundle\Exception\NotFoundAccessTokenException;
use AnzuSystems\AuthBundle\Helper\ConditionHelper;
use AnzuSystems\AuthBundle\Model\RefreshTokenDto;
use DateTimeImmutable;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\Token\Parser;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use function Symfony\Component\String\u;

final class HttpUtil
{
    private const COOKIE_DEVICE_ID_TTL = 31_536_000; // 1 year

    public function __construct(
        private readonly CookieConfiguration $cookieConfiguration,
        private readonly JwtConfiguration $jwtConfiguration,
        private readonly string $authRedirectDefaultUrl,
        private readonly string $authRedirectQueryUrlAllowedPattern,
    ) {
    }

    public function getAuthRedirectUrlFromRequest(Request $request): string
    {
        $redirectUrl = $this->authRedirectDefaultUrl;
        $redirectUrlFromQueryParam = (string) $request->query->get('url');
        if ($redirectUrlFromQueryParam
            && preg_match('~' . $this->authRedirectQueryUrlAllowedPattern . '~i', $redirectUrlFromQueryParam)
        ) {
            $redirectUrl = $redirectUrlFromQueryParam;
        }

        return $redirectUrl;
    }

    /**
     * @throws NotFoundAccessTokenException
     */
    public function grabJwtFromRequest(Request $request): Token\Plain
    {
        $jwt = $this->getPlainAccessTokenFromRequest($request);

        if (empty($jwt)) {
            throw NotFoundAccessTokenException::create();
        }

        /** @var Token\Plain $token */
        $token = (new Parser(new JoseEncoder()))->parse($jwt);

        return $token;
    }

    /**
     * @return array{0: string, 1: string} - [userId, tokenHash]
     *
     * @throws InvalidRefreshTokenException
     */
    public function grabRefreshTokenFromRequest(Request $request): array
    {
        $rawRefreshToken = (string) $request->cookies->get($this->cookieConfiguration->getRefreshTokenCookieName());
        if (empty($rawRefreshToken)) {
            throw InvalidRefreshTokenException::create();
        }
        [$userId, $tokenHash] = explode(RefreshTokenDto::REFRESH_TOKEN_SEPARATOR, $rawRefreshToken, 2);
        if (ConditionHelper::isOneOfVariablesEmpty($userId, $tokenHash)) {
            throw InvalidRefreshTokenException::create();
        }

        return [$userId, $tokenHash];
    }

    public function grabDeviceIdFromRequest(Request $request): string
    {
        return (string) $request->cookies->get($this->cookieConfiguration->getDeviceIdCookieName());
    }

    /**
     * @throws InvalidJwtException
     */
    public function storeJwtOnResponse(Response $response, Token $token, DateTimeImmutable $expiresAt = null): void
    {
        $rawToken = $token->toString();
        [$header, $claims, $signature] = explode('.', $rawToken, 3);

        if (ConditionHelper::isOneOfVariablesEmpty($header, $claims, $signature)) {
            throw InvalidJwtException::create($rawToken);
        }

        $lifetime = $expiresAt?->getTimestamp() ?? $this->jwtConfiguration->getLifetime();
        $payloadCookie = $this->createCookie(
            $this->cookieConfiguration->getJwtPayloadCookieName(),
            $header . '.' . $claims,
            $lifetime,
            false
        );
        $signatureCookie = $this->createCookie(
            $this->cookieConfiguration->getJwtSignatureCookieName(),
            $signature,
            $lifetime
        );

        $response->headers->setCookie($payloadCookie);
        $response->headers->setCookie($signatureCookie);
    }

    public function storeRefreshTokenOnResponse(Response $response, RefreshTokenDto $refreshTokenDto): void
    {
        $refreshTokenCookie = $this->createCookie(
            $this->cookieConfiguration->getRefreshTokenCookieName(),
            $refreshTokenDto->toString(),
            $this->cookieConfiguration->getRefreshTokenLifetime(),
        );

        $refreshTokenExistenceCookie = $this->createCookie(
            $this->cookieConfiguration->getRefreshTokenExistenceCookieName(),
            '1',
            $this->cookieConfiguration->getRefreshTokenLifetime(),
            false
        );

        $response->headers->setCookie($refreshTokenCookie);
        $response->headers->setCookie($refreshTokenExistenceCookie);
    }

    public function storeDeviceIdOnResponse(Response $response, string $deviceId): void
    {
        $refreshTokenCookie = $this->createCookie(
            $this->cookieConfiguration->getDeviceIdCookieName(),
            $deviceId,
            self::COOKIE_DEVICE_ID_TTL,
        );

        $response->headers->setCookie($refreshTokenCookie);
    }

    public function destroyJwtOnResponse(Response $response): void
    {
        $response->headers->clearCookie(
            name: $this->cookieConfiguration->getJwtPayloadCookieName(),
            domain: $this->cookieConfiguration->getDomain(),
        );
        $response->headers->clearCookie(
            name: $this->cookieConfiguration->getJwtSignatureCookieName(),
            domain: $this->cookieConfiguration->getDomain(),
        );
    }

    public function destroyRefreshTokenOnResponse(Response $response): void
    {
        $response->headers->clearCookie(
            name: $this->cookieConfiguration->getRefreshTokenCookieName(),
            domain: $this->cookieConfiguration->getDomain(),
        );
        $response->headers->clearCookie(
            name: $this->cookieConfiguration->getRefreshTokenExistenceCookieName(),
            domain: $this->cookieConfiguration->getDomain(),
        );
    }

    private function createCookie(
        string $name,
        string $value,
        int $ttl,
        bool $httpOnly = true,
    ): Cookie {
        return Cookie::create(
            $name,
            $value,
            time() + $ttl,
            '/',
            $this->cookieConfiguration->getDomain(),
            $this->cookieConfiguration->isSecure(),
            $httpOnly,
            false,
            Cookie::SAMESITE_STRICT
        );
    }

    private function getPlainAccessTokenFromRequest(Request $request): ?string
    {
        if ($request->headers->has('Authorization')) {
            return u((string) $request->headers->get('Authorization'))
                ->replaceMatches('~Bearer[\s+]~', '')
                ->trim()
                ->toString();
        }

        if ($request->cookies->has($this->cookieConfiguration->getJwtPayloadCookieName())
            && $request->cookies->has($this->cookieConfiguration->getJwtSignatureCookieName())
        ) {
            return u((string) $request->cookies->get($this->cookieConfiguration->getJwtPayloadCookieName()))
                ->append('.')
                ->append((string) $request->cookies->get($this->cookieConfiguration->getJwtSignatureCookieName()))
                ->trim()
                ->toString();
        }

        return '';
    }
}

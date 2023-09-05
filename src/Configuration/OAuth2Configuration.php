<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Configuration;

use AnzuSystems\AuthBundle\Model\SsoUserDto;
use Psr\Cache\CacheItemPoolInterface;

final class OAuth2Configuration
{
    public const SSO_USER_ID_PLACEHOLDER_URL = '{userId}';

    public function __construct(
        private readonly string $ssoAccessTokenUrl,
        private readonly string $ssoAuthorizeUrl,
        private readonly string $ssoRedirectUrl,
        private readonly string $ssoUserInfoUrl,
        /** @var class-string<SsoUserDto> */
        private readonly string $ssoUserInfoClass,
        private readonly string $ssoClientId,
        private readonly string $ssoClientSecret,
        private readonly string $ssoPublicCert,
        private readonly array $ssoScopes,
        private readonly string $ssoScopeDelimiter,
        private readonly CacheItemPoolInterface $accessTokenCachePool,
    ) {
    }

    public function getSsoAccessTokenUrl(): string
    {
        return $this->ssoAccessTokenUrl;
    }

    public function getSsoAuthorizeUrl(): string
    {
        return $this->ssoAuthorizeUrl;
    }

    public function getSsoUserInfoUrl(?string $userId): string
    {
        if (!$userId) {
            return $this->ssoUserInfoUrl;
        }

        return str_replace(self::SSO_USER_ID_PLACEHOLDER_URL, $userId, $this->ssoUserInfoUrl);
    }

    /**
     * @return class-string<SsoUserDto>
     */
    public function getSsoUserInfoClass(): string
    {
        return $this->ssoUserInfoClass;
    }

    public function getResolvedSsoAuthorizeUrl(string $state): string
    {
        return $this->getSsoAuthorizeUrl() . '?' . http_build_query(
            array_filter([
                'client_id' => $this->getSsoClientId(),
                'response_type' => 'code',
                'state' => $state,
                'redirect_uri' => $this->getSsoRedirectUrl(),
                'scope' => implode($this->ssoScopeDelimiter, $this->getSsoScopes()),
            ])
        );
    }

    public function getSsoRedirectUrl(): string
    {
        return $this->ssoRedirectUrl;
    }

    public function getSsoClientId(): string
    {
        return $this->ssoClientId;
    }

    public function getSsoClientSecret(): string
    {
        return $this->ssoClientSecret;
    }

    public function getSsoPublicCert(): string
    {
        return $this->ssoPublicCert;
    }

    /**
     * @return list<string>
     */
    public function getSsoScopes(): array
    {
        return $this->ssoScopes;
    }

    public function getAccessTokenCachePool(): CacheItemPoolInterface
    {
        return $this->accessTokenCachePool;
    }
}

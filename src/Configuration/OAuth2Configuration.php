<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Configuration;

final class OAuth2Configuration
{
    public function __construct(
        private readonly string $ssoAccessTokenUrl,
        private readonly string $ssoAuthorizeUrl,
        private readonly string $ssoRedirectUrl,
        private readonly string $ssoClientId,
        private readonly string $ssoClientSecret,
        private readonly string $ssoPublicCert,
        private readonly array $ssoScopes,
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

    public function getResolvedSsoAuthorizeUrl(string $state): string
    {
        return $this->getSsoAuthorizeUrl() . '?' . http_build_query(
            array_filter([
                'client_id' => $this->getSsoClientId(),
                'response_type' => 'code',
                'state' => $state,
                'redirect_uri' => $this->getSsoRedirectUrl(),
                'scope' => implode(',', $this->getSsoScopes()),
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
}

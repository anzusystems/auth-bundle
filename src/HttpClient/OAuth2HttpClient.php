<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\HttpClient;

use AnzuSystems\AuthBundle\Configuration\OAuth2Configuration;
use AnzuSystems\AuthBundle\Exception\UnsuccessfulAccessTokenRequestException;
use AnzuSystems\AuthBundle\Exception\UnsuccessfulUserInfoRequestException;
use AnzuSystems\AuthBundle\Model\AccessTokenDto;
use AnzuSystems\AuthBundle\Model\AccessTokenResponseDto;
use AnzuSystems\AuthBundle\Model\OpaqueAccessTokenResponseDto;
use AnzuSystems\AuthBundle\Model\SsoUserDto;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use AnzuSystems\SerializerBundle\Serializer;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OAuth2HttpClient
{
    private const CLIENT_SERVICE_ACCESS_TOKEN_CACHE_KEY = 'sso_access_token_client_service';

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly OAuth2Configuration $configuration,
        private readonly Serializer $serializer,
    ) {
    }

    /**
     * @throws UnsuccessfulAccessTokenRequestException
     */
    public function requestAccessTokenByAuthCode(string $code): AccessTokenDto
    {
        $accessToken = $this->sendTokenRequest($this->configuration->getSsoAccessTokenUrl(), [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $this->configuration->getSsoClientId(),
            'client_secret' => $this->configuration->getSsoClientSecret(),
            'redirect_uri' => $this->configuration->getSsoRedirectUrl(),
        ]);

        $this->storeAccessTokenToCache($this->getAccessTokenCacheItem(), $accessToken);

        return $accessToken;
    }

    /**
     * @throws UnsuccessfulAccessTokenRequestException
     * @throws UnsuccessfulUserInfoRequestException
     */
    public function getSsoUserInfo(?string $id = null): SsoUserDto
    {
        try {
            $response = $this->client->request(
                method: Request::METHOD_GET,
                url: $this->configuration->getSsoUserInfoUrl($id),
                options: [
                    'auth_bearer' => $this->requestAccessTokenForClientService()->getAccessToken(),
                ]
            );

            return $this->serializer->deserialize($response->getContent(), $this->configuration->getSsoUserInfoClass());
        } catch (ExceptionInterface $exception) {
            throw UnsuccessfulUserInfoRequestException::create('User info request failed!', $exception);
        } catch (SerializerException $exception) {
            throw UnsuccessfulUserInfoRequestException::create('User info response deserialization failed!', $exception);
        }
    }

    /**
     * @throws UnsuccessfulAccessTokenRequestException
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    private function requestAccessTokenForClientService(): AccessTokenDto
    {
        $accessTokenCacheItem = $this->getAccessTokenCacheItem();
        if ($accessTokenCacheItem->isHit()) {
            return $accessTokenCacheItem->get();
        }

        $accessToken = $this->sendTokenRequest($this->configuration->getSsoAccessTokenUrl(), [
            'grant_type' => 'client_credentials',
            'client_id' => $this->configuration->getSsoClientId(),
            'client_secret' => $this->configuration->getSsoClientSecret(),
        ]);

        $this->storeAccessTokenToCache($accessTokenCacheItem, $accessToken);

        return $accessToken;
    }

    /**
     * @throws UnsuccessfulAccessTokenRequestException
     */
    private function sendTokenRequest(string $url, array $bodyParameters): AccessTokenDto
    {
        try {
            $response = $this->client->request(Request::METHOD_POST, $url, ['body' => $bodyParameters]);

            if ($this->configuration->isAccessTokenConsideredJwt()) {
                return AccessTokenDto::createFromJwtAccessTokenResponse(
                    $this->serializer->deserialize($response->getContent(), AccessTokenResponseDto::class)
                );
            }

            return AccessTokenDto::createFromOpaqueAccessTokenResponse(
                $this->serializer->deserialize($response->getContent(), OpaqueAccessTokenResponseDto::class)
            );
        } catch (ExceptionInterface $exception) {
            throw UnsuccessfulAccessTokenRequestException::create('Token request failed!', $exception);
        } catch (SerializerException $exception) {
            throw UnsuccessfulAccessTokenRequestException::create('Invalid jwt token response!', $exception);
        }
    }

    private function getAccessTokenCacheItem(): CacheItemInterface
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        return $this->configuration->getAccessTokenCachePool()->getItem(
            self::CLIENT_SERVICE_ACCESS_TOKEN_CACHE_KEY
        );
    }

    private function storeAccessTokenToCache(
        CacheItemInterface $accessTokenCacheItem,
        AccessTokenDto $accessToken
    ): void {
        $cachePool = $this->configuration->getAccessTokenCachePool();

        $accessTokenCacheItem->set($accessToken);
        $accessTokenCacheItem->expiresAt($accessToken->getExpiresAt());
        $cachePool->save($accessTokenCacheItem);
    }
}

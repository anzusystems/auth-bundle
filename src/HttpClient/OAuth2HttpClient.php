<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\HttpClient;

use AnzuSystems\AuthBundle\Configuration\OAuth2Configuration;
use AnzuSystems\AuthBundle\Exception\InvalidJwtException;
use AnzuSystems\AuthBundle\Exception\UnsuccessfulOAuth2RequestException;
use AnzuSystems\AuthBundle\Helper\ConditionHelper;
use AnzuSystems\AuthBundle\Model\OAuth2TokenResponseDto;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OAuth2HttpClient
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly OAuth2Configuration $configuration,
    ) {
    }

    /**
     * @throws UnsuccessfulOAuth2RequestException
     */
    public function requestAccessToken(string $code): OAuth2TokenResponseDto
    {
        return $this->sendTokenRequest($this->configuration->getSsoAccessTokenUrl(), [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $this->configuration->getSsoClientId(),
            'client_secret' => $this->configuration->getSsoClientSecret(),
            'redirect_uri' => $this->configuration->getSsoRedirectUrl(),
        ]);
    }

    /**
     * @throws UnsuccessfulOAuth2RequestException
     */
    private function sendTokenRequest(string $url, array $bodyParameters): OAuth2TokenResponseDto
    {
        try {
            $response = $this->client->request(Request::METHOD_POST, $url, ['body' => $bodyParameters]);

            /** @var array{access_token?: string, refresh_token?: string} $content */
            $content = $response->toArray();
            $content['refresh_token'] ??= $bodyParameters['refresh_token'];

            $tokenResponseDto = OAuth2TokenResponseDto::createFromArray($content);

            if (ConditionHelper::isOneOfVariablesEmpty(
                $tokenResponseDto->getAccessToken(),
                $tokenResponseDto->getRefreshToken()
            )) {
                throw UnsuccessfulOAuth2RequestException::create(
                    'Missing required response parameter "access_token" or "refresh_token"'
                );
            }

            return $tokenResponseDto;
        } catch (ExceptionInterface $exception) {
            throw UnsuccessfulOAuth2RequestException::create('Token request failed!', $exception);
        } catch (InvalidJwtException $exception) {
            throw UnsuccessfulOAuth2RequestException::create('Invalid jwt token!', $exception);
        }
    }
}

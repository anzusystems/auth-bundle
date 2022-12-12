<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Domain\Process;

use AnzuSystems\AuthBundle\Contracts\OAuth2AuthUserRepositoryInterface;
use AnzuSystems\AuthBundle\Exception\InvalidJwtException;
use AnzuSystems\AuthBundle\Exception\MissingConfigurationException;
use AnzuSystems\AuthBundle\Exception\UnsuccessfulOAuth2RequestException;
use AnzuSystems\AuthBundle\HttpClient\OAuth2HttpClient;
use AnzuSystems\AuthBundle\Util\HttpUtil;
use Exception;
use Lcobucci\JWT\Token\RegisteredClaims;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class GrantAccessByOAuth2TokenProcess
{
    public function __construct(
        private readonly OAuth2HttpClient $OAuth2HttpClient,
        private readonly GrantAccessOnResponseProcess $grantAccessOnResponseProcess,
        private readonly ValidateOAuth2AccessTokenProcess $validateOAuth2AccessTokenProcess,
        private readonly OAuth2AuthUserRepositoryInterface $OAuth2AuthUserRepository,
        private readonly HttpUtil $httpUtil,
    ) {
    }

    /**
     * @throws UnsuccessfulOAuth2RequestException
     * @throws InvalidJwtException
     * @throws MissingConfigurationException
     */
    public function execute(Request $request): Response
    {
        $code = (string) $request->query->get('code');
        $ssoJwt = $this->OAuth2HttpClient->requestAccessToken($code);
        $this->validateOAuth2AccessTokenProcess->execute($ssoJwt->getAccessToken());

        $ssoUserId = (string) $ssoJwt->getAccessToken()->claims()->get(RegisteredClaims::SUBJECT);
        $authUser = $this->OAuth2AuthUserRepository->findOneBySsoUserId($ssoUserId);
        if (null === $authUser || false === $authUser->isEnabled()) {
            throw new Exception('auth');
        }

        $redirectUrl = $this->httpUtil->getAuthRedirectUrlFromRequest($request);
        $response = new RedirectResponse($redirectUrl);

        return $this->grantAccessOnResponseProcess->execute($authUser->getAuthId(), $request, $response);
    }
}

<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Domain\Process\OAuth2;

use AnzuSystems\AuthBundle\Contracts\OAuth2AuthUserRepositoryInterface;
use AnzuSystems\AuthBundle\Domain\Process\GrantAccessOnResponseProcess;
use AnzuSystems\AuthBundle\Exception\InvalidJwtException;
use AnzuSystems\AuthBundle\Exception\UnsuccessfulAccessTokenRequestException;
use AnzuSystems\AuthBundle\Exception\UnsuccessfulUserInfoRequestException;
use AnzuSystems\AuthBundle\HttpClient\OAuth2HttpClient;
use AnzuSystems\AuthBundle\Model\Enum\UserOAuthLoginState;
use AnzuSystems\AuthBundle\Util\HttpUtil;
use AnzuSystems\CommonBundle\Log\Factory\LogContextFactory;
use AnzuSystems\CommonBundle\Traits\SerializerAwareTrait;
use AnzuSystems\Contracts\Exception\AnzuException;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Exception;
use Lcobucci\JWT\Token\RegisteredClaims;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class GrantAccessByOAuth2TokenProcess
{
    use SerializerAwareTrait;

    public const AUTH_METHOD_SSO_ID = 'sso_id';
    public const AUTH_METHOD_SSO_EMAIL = 'sso_email';

    public function __construct(
        private readonly OAuth2HttpClient $OAuth2HttpClient,
        private readonly GrantAccessOnResponseProcess $grantAccessOnResponseProcess,
        private readonly ValidateOAuth2AccessTokenProcess $validateOAuth2AccessTokenProcess,
        private readonly OAuth2AuthUserRepositoryInterface $oAuth2AuthUserRepository,
        private readonly HttpUtil $httpUtil,
        private readonly LoggerInterface $appLogger,
        private readonly LogContextFactory $contextFactory,
        private readonly string $authMethod,
    ) {
    }

    /**
     * @throws SerializerException
     * @throws AnzuException
     */
    public function execute(Request $request): Response
    {
        $code = (string) $request->query->get('code');

        try {
            $ssoJwt = $this->OAuth2HttpClient->requestAccessTokenByAuthCode($code);
        } catch (UnsuccessfulAccessTokenRequestException $exception) {
            $this->logException($request, $exception);

            return $this->createRedirectResponseForRequest($request, UserOAuthLoginState::FailureSsoCommunicationFailed);
        }

        try {
            $this->validateOAuth2AccessTokenProcess->execute($ssoJwt->getAccessToken());
        } catch (InvalidJwtException $exception) {
            $this->logException($request, $exception);

            return $this->createRedirectResponseForRequest($request, UserOAuthLoginState::FailureUnauthorized);
        }

        if (self::AUTH_METHOD_SSO_EMAIL === $this->authMethod) {
            try {
                $ssoUser = $this->OAuth2HttpClient->getSsoUserInfo();
            } catch (UnsuccessfulUserInfoRequestException | UnsuccessfulAccessTokenRequestException $exception) {
                $this->logException($request, $exception);

                return $this->createRedirectResponseForRequest($request, UserOAuthLoginState::FailureSsoCommunicationFailed);
            }
            $authUser = $this->oAuth2AuthUserRepository->findOneBySsoEmail($ssoUser->getEmail());
        } else if (self::AUTH_METHOD_SSO_ID === $this->authMethod) {
            $ssoUserId = (string)$ssoJwt->getAccessToken()->claims()->get(RegisteredClaims::SUBJECT);
            $authUser = $this->oAuth2AuthUserRepository->findOneBySsoUserId($ssoUserId);
        } else {
            throw new AnzuException(sprintf('Unknown auth method "%s".', $this->authMethod));
        }

        if (null === $authUser || false === $authUser->isEnabled()) {
            return $this->createRedirectResponseForRequest($request, UserOAuthLoginState::FailureUnauthorized);
        }

        try {
            $response = $this->createRedirectResponseForRequest($request, UserOAuthLoginState::Success);

            return $this->grantAccessOnResponseProcess->execute($authUser->getAuthId(), $request, $response);
        } catch (Exception $exception) {
            $this->logException($request, $exception);

            return $this->createRedirectResponseForRequest($request, UserOAuthLoginState::FailureInternalError);
        }
    }

    /**
     * @throws SerializerException
     */
    private function logException(Request $request, Throwable $throwable): void
    {
        $context = $this->contextFactory->buildFromRequest($request);
        $this->appLogger->error('[Authorization] ' . $throwable->getMessage(), $this->serializer->toArray($context));
    }

    private function createRedirectResponseForRequest(Request $request, UserOAuthLoginState $loginState): RedirectResponse
    {
        $redirectUrl = $this->httpUtil->getAuthRedirectUrlFromRequest($request);
        $redirectUrl .= '?loginState=' . $loginState->toString();

        return new RedirectResponse($redirectUrl);
    }
}

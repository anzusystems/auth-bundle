<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Domain\Process\OAuth2;

use AnzuSystems\AuthBundle\Contracts\AnzuAuthUserInterface;
use AnzuSystems\AuthBundle\Contracts\OAuth2AuthUserRepositoryInterface;
use AnzuSystems\AuthBundle\Domain\Process\GrantAccessOnResponseProcess;
use AnzuSystems\AuthBundle\Event\AuthTargetUrlEvent;
use AnzuSystems\AuthBundle\Exception\InvalidJwtException;
use AnzuSystems\AuthBundle\Exception\UnsuccessfulAccessTokenRequestException;
use AnzuSystems\AuthBundle\Exception\UnsuccessfulUserInfoRequestException;
use AnzuSystems\AuthBundle\HttpClient\OAuth2HttpClient;
use AnzuSystems\AuthBundle\Model\AccessTokenDto;
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
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Throwable;

final class GrantAccessByOAuth2TokenProcess
{
    use SerializerAwareTrait;

    public const string AUTH_METHOD_SSO_ID = 'sso_id';
    public const string AUTH_METHOD_SSO_EMAIL = 'sso_email';
    public const string LOGIN_STATE_QUERY_PARAM = 'loginState';
    public const string TIMESTAMP_QUERY_PARAM = 'timestamp';

    public function __construct(
        private readonly OAuth2HttpClient $OAuth2HttpClient,
        private readonly GrantAccessOnResponseProcess $grantAccessOnResponseProcess,
        private readonly ValidateOAuth2AccessTokenProcess $validateOAuth2AccessTokenProcess,
        private readonly OAuth2AuthUserRepositoryInterface $oAuth2AuthUserRepository,
        private readonly HttpUtil $httpUtil,
        private readonly LoggerInterface $appLogger,
        private readonly LogContextFactory $contextFactory,
        private readonly string $authMethod,
        private readonly EventDispatcherInterface $eventDispatcher,
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
            $accessTokenDto = $this->OAuth2HttpClient->requestAccessTokenByAuthCode($code);
        } catch (UnsuccessfulAccessTokenRequestException $exception) {
            $this->logException($request, $exception);

            return $this->createRedirectResponseForRequest($request, UserOAuthLoginState::FailureSsoCommunicationFailed);
        }

        $jwt = $accessTokenDto->getJwt();
        if ($jwt) {
            // validate jwt
            try {
                $this->validateOAuth2AccessTokenProcess->execute($jwt);
            } catch (InvalidJwtException $exception) {
                $this->logException($request, $exception);

                return $this->createRedirectResponseForRequest($request, UserOAuthLoginState::FailureUnauthorized);
            }
        }

        try {
            $authUser = $this->getAuthUser($accessTokenDto);
        } catch (UnsuccessfulUserInfoRequestException | UnsuccessfulAccessTokenRequestException $exception) {
            $this->logException($request, $exception);

            return $this->createRedirectResponseForRequest(
                $request,
                UserOAuthLoginState::FailureSsoCommunicationFailed
            );
        }

        if (null === $authUser || false === $authUser->isEnabled()) {
            return $this->createRedirectResponseForRequest($request, UserOAuthLoginState::FailureUnauthorized);
        }

        try {
            $response = $this->createRedirectResponseForRequest($request, UserOAuthLoginState::Success, $authUser);

            return $this->grantAccessOnResponseProcess->execute($authUser->getAuthId(), $request, $response);
        } catch (Exception $exception) {
            $this->logException($request, $exception);

            return $this->createRedirectResponseForRequest($request, UserOAuthLoginState::FailureInternalError);
        }
    }

    public function createRedirectResponseForRequest(Request $request, UserOAuthLoginState $loginState, ?AnzuAuthUserInterface $authUser = null): RedirectResponse
    {
        $redirectUrl = $this->httpUtil->getAuthRedirectUrlFromRequest($request);
        $redirectUrl .= '?';
        $redirectUrl .= http_build_query([
            self::LOGIN_STATE_QUERY_PARAM => $loginState->toString(),
            self::TIMESTAMP_QUERY_PARAM => time(),
        ]);

        $event = new AuthTargetUrlEvent($redirectUrl, $request, $loginState, $authUser);
        $this->eventDispatcher->dispatch($event, AuthTargetUrlEvent::NAME);

        return new RedirectResponse($event->getTargetUrl());
    }

    /**
     * @throws SerializerException
     */
    private function logException(Request $request, Throwable $throwable): void
    {
        $context = $this->contextFactory->buildFromRequest($request);

        $content = $throwable->getTraceAsString();
        $prevException = $throwable->getPrevious();
        if ($prevException) {
            $content .= "\nPrevious exception:\n" . $prevException->getTraceAsString();
        }
        if ($prevException instanceof HttpExceptionInterface) {
            $response = $prevException->getResponse();
            try {
                $context
                    ->setHttpStatus($response->getStatusCode())
                    ->setResponse($response->getContent())
                ;
            } catch (ExceptionInterface $responseException) {
                $context
                    ->setResponse(sprintf('Failed to retrieve a response content! (error: %s)', $responseException->getMessage()))
                ;
            }
        }
        if ($throwable instanceof UnsuccessfulAccessTokenRequestException) {
            $context->setParams($throwable->getBodyParams());
        }

        $context->setContent($content);
        $arrayContext = $this->serializer->toArray($context);
        if (false === is_array($arrayContext)) {
            $arrayContext = [];
        }
        $this->appLogger->error('[Authorization] ' . $throwable->getMessage(), $arrayContext);
    }

    /**
     * @throws AnzuException
     * @throws UnsuccessfulAccessTokenRequestException
     * @throws UnsuccessfulUserInfoRequestException
     */
    private function getAuthUser(AccessTokenDto $accessTokenDto): ?AnzuAuthUserInterface
    {
        if (self::AUTH_METHOD_SSO_EMAIL === $this->authMethod) {
            // fetch user info
            $ssoUser = $this->OAuth2HttpClient->getCurrentSsoUserInfo($accessTokenDto);

            return $this->oAuth2AuthUserRepository->findOneBySsoEmail($ssoUser->getEmail());
        }

        if (self::AUTH_METHOD_SSO_ID === $this->authMethod) {
            // prefer to use the jwt
            if ($accessTokenDto->getJwt()) {
                $ssoUserId = (string) $accessTokenDto->getJwt()->claims()->get(RegisteredClaims::SUBJECT);

                return $this->oAuth2AuthUserRepository->findOneBySsoUserId($ssoUserId);
            }

            // otherwise fetch user info
            $ssoUser = $this->OAuth2HttpClient->getCurrentSsoUserInfo($accessTokenDto);

            return $this->oAuth2AuthUserRepository->findOneBySsoUserId($ssoUser->getId());
        }

        throw new AnzuException(sprintf('Unknown auth method "%s".', $this->authMethod));
    }
}

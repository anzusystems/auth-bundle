<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Controller\Api;

use AnzuSystems\AuthBundle\Configuration\OAuth2Configuration;
use AnzuSystems\AuthBundle\Domain\Process\GrantAccessByOAuth2TokenProcess;
use AnzuSystems\AuthBundle\Domain\Process\RefreshTokenProcess;
use AnzuSystems\AuthBundle\Exception\InvalidJwtException;
use AnzuSystems\AuthBundle\Exception\MissingConfigurationException;
use AnzuSystems\AuthBundle\Exception\UnsuccessfulOAuth2RequestException;
use AnzuSystems\AuthBundle\Util\StatelessTokenUtil;
use OpenApi\Attributes as OA;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(name: 'auth_')]
#[OA\Tag('Authorization')]
final class OAuth2AuthController extends AbstractAuthController
{
    public function __construct(
        private readonly OAuth2Configuration $configuration,
        private readonly StatelessTokenUtil $statelessTokenUtil,
        private readonly GrantAccessByOAuth2TokenProcess $grantAccessByOAuth2TokenProcess,
        RefreshTokenProcess $refreshTokenProcess,
        EventDispatcherInterface $eventDispatcher,
    ) {
        parent::__construct($refreshTokenProcess, $eventDispatcher);
    }

    #[Route('login', name: 'login', methods: [Request::METHOD_GET])]
    public function login(Request $request): RedirectResponse
    {
        $token = $this->statelessTokenUtil->createForRequest($request);

        return new RedirectResponse(
            $this->configuration->getResolvedSsoAuthorizeUrl($token)
        );
    }

    /**
     * @throws UnsuccessfulOAuth2RequestException
     * @throws MissingConfigurationException
     * @throws InvalidJwtException
     */
    #[Route('authorize', name: 'authorize', methods: [Request::METHOD_GET])]
    public function callbackAuthorize(Request $request): Response
    {
        $hash = $request->query->get('state');
        if (empty($hash) || $this->statelessTokenUtil->isNotValidForRequest($request, $hash)) {
            throw $this->createAccessDeniedException('Invalid state token.');
        }

        return $this->grantAccessByOAuth2TokenProcess->execute($request);
    }
}

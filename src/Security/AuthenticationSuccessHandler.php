<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Security;

use AnzuSystems\AuthBundle\Domain\Process\GrantAccessOnResponseProcess;
use AnzuSystems\AuthBundle\Exception\InvalidJwtException;
use AnzuSystems\AuthBundle\Exception\MissingConfigurationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

final class AuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private readonly GrantAccessOnResponseProcess $grantAccessOnResponseProcess,
    ) {
    }

    /**
     * @throws MissingConfigurationException
     * @throws InvalidJwtException
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        /** @var UserInterface $user */
        $user = $token->getUser();

        return $this->grantAccessOnResponseProcess->execute(
            $user->getUserIdentifier(),
            $request
        );
    }
}

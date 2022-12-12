<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Security;

use AnzuSystems\Contracts\AnzuApp;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

final class AuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($exception instanceof BadCredentialsException) {
            return new JsonResponse(
                data: [
                    'error' => 'bad_credentials',
                    'detail' => $exception->getMessage(),
                    'contextId' => AnzuApp::getContextId(),
                ],
                status: Response::HTTP_BAD_REQUEST
            );
        }

        return new JsonResponse(
            data: [
                'error' => 'unknown_error',
                'detail' => $exception->getMessage(),
                'contextId' => AnzuApp::getContextId(),
            ],
            status: Response::HTTP_BAD_REQUEST
        );
    }
}

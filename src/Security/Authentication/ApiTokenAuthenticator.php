<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Security\Authentication;

use AnzuSystems\AuthBundle\Contracts\ApiTokenUserInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use UnexpectedValueException;
use function Symfony\Component\String\u;

/**
 * Authenticator used for user implemented ApiTokenUserInterface.
 */
final class ApiTokenAuthenticator extends AbstractAuthenticator
{
    public function supports(Request $request): bool
    {
        return $request->headers->has('Authorization');
    }

    public function authenticate(Request $request): Passport
    {
        [$userId, $token] = $this->getCredentials($request);
        if (empty($userId) || empty($token)) {
            throw new AuthenticationException('Unauthorized request!');
        }
        $credentialsChecker = function (mixed $token, UserInterface $user): bool {
            return $this->checkCredentials($token, $user);
        };

        return new Passport(
            new UserBadge($userId->toString()),
            new CustomCredentials($credentialsChecker, $token->toString())
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        return new JsonResponse([
            'message' => 'The resource owner or authorization server denied the request.',
        ], Response::HTTP_UNAUTHORIZED);
    }

    private function getCredentials(Request $request): array
    {
        $credentials = u((string) $request->headers->get('Authorization'))
            ->replaceMatches('~Bearer[\s+]~', '')
            ->trim()
            ->split(':', 2);
        if (2 === count($credentials)) {
            return $credentials;
        }

        return [null, null];
    }

    private function checkCredentials(mixed $token, UserInterface $user): bool
    {
        if (false === is_string($token)) {
            throw new UnexpectedValueException(sprintf('Unsupported API token "%s".', get_debug_type($token)));
        }
        if (false === $user instanceof ApiTokenUserInterface) {
            throw new UnexpectedValueException(sprintf('Unsupported user "%s".', get_debug_type($user)));
        }

        return password_verify($token, (string) $user->getApiToken());
    }
}

<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Security\Authentication;

use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Cache\PersonalAccessTokenAuthCache;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Manager\PersonalAccessTokenManager;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Model\CachedPersonalAccessToken;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Repository\PersonalAccessTokenRepository;
use AnzuSystems\AuthBundle\Entity\AbstractPersonalAccessToken;
use AnzuSystems\CommonBundle\Mcp\McpRateLimiter;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Entity\AnzuUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final class PersonalAccessTokenAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    private const string AUTHORIZATION_HEADER = 'Authorization';
    private const string BEARER_PREFIX = 'Bearer ';
    private const string WWW_AUTHENTICATE_HEADER = 'WWW-Authenticate';
    private const string WWW_AUTHENTICATE_SCHEME = 'Bearer';
    private const string PASSPORT_ATTRIBUTE_TOKEN = 'personal_access_token';
    private const string RATE_LIMIT_KEY_PREFIX = 'pat_';

    /**
     * @param class-string<AnzuUser> $userEntityClass
     */
    public function __construct(
        private readonly PersonalAccessTokenRepository $personalAccessTokenRepo,
        private readonly PersonalAccessTokenManager $personalAccessTokenManager,
        private readonly PersonalAccessTokenAuthCache $authCache,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $userEntityClass,
    ) {
    }

    public function supports(Request $request): bool
    {
        $header = (string) $request->headers->get(self::AUTHORIZATION_HEADER);
        if (false === $this->hasBearerScheme($header)) {
            return false;
        }

        return str_starts_with($this->extractPlainToken($header), AbstractPersonalAccessToken::TOKEN_PREFIX);
    }

    public function authenticate(Request $request): Passport
    {
        $plainToken = $this->extractPlainToken(
            (string) $request->headers->get(self::AUTHORIZATION_HEADER)
        );
        $tokenHash = AbstractPersonalAccessToken::hashToken($plainToken);
        $cacheVersion = $this->authCache->getInvalidationVersion($tokenHash);
        $cachedToken = $this->authCache->getToken($tokenHash, $cacheVersion)
            ?? $this->authenticateAgainstDatabase($tokenHash, $cacheVersion);
        $user = $this->entityManager->find($this->userEntityClass, $cachedToken->userId);
        if (false === ($user instanceof AnzuUser)) {
            throw new AuthenticationException(sprintf('User (%d) not found!', $cachedToken->userId));
        }
        if (false === $user->isEnabled()) {
            throw new AuthenticationException(sprintf('User (%d) is not active or is disabled!', (int) $user->getId()));
        }

        $passport = new SelfValidatingPassport(
            new UserBadge((string) $user->getId(), static fn (): AnzuUser => $user)
        );
        $passport->setAttribute(self::PASSPORT_ATTRIBUTE_TOKEN, $cachedToken);

        return $passport;
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $token = parent::createToken($passport, $firewallName);
        $cachedToken = $passport->getAttribute(self::PASSPORT_ATTRIBUTE_TOKEN);
        if ($cachedToken instanceof CachedPersonalAccessToken) {
            $token->setAttribute(
                McpRateLimiter::TOKEN_ATTRIBUTE_KEY,
                self::RATE_LIMIT_KEY_PREFIX . $cachedToken->personalAccessTokenId,
            );
            $token->setAttribute(McpRateLimiter::TOKEN_ATTRIBUTE_LIMIT, $cachedToken->rateLimit);
        }

        return $token;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return $this->createUnauthorizedResponse();
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return $this->createUnauthorizedResponse();
    }

    private function authenticateAgainstDatabase(string $tokenHash, int $cacheVersion): CachedPersonalAccessToken
    {
        try {
            $personalAccessToken = $this->personalAccessTokenRepo->findOneActiveByTokenHash($tokenHash);
        } catch (NonUniqueResultException $exception) {
            throw new AuthenticationException('Ambiguous personal access token!', 0, $exception);
        }
        if (null === $personalAccessToken) {
            throw new AuthenticationException('Invalid personal access token!');
        }
        $this->updateLastUsedAtThrottled($personalAccessToken);
        $cachedToken = CachedPersonalAccessToken::fromEntity($personalAccessToken);
        $this->authCache->storeToken($tokenHash, $cacheVersion, $cachedToken, $personalAccessToken->getExpiresAt());

        return $cachedToken;
    }

    private function createUnauthorizedResponse(): JsonResponse
    {
        return new JsonResponse(
            [
                'message' => 'The resource owner or authorization server denied the request.',
            ],
            Response::HTTP_UNAUTHORIZED,
            [
                self::WWW_AUTHENTICATE_HEADER => self::WWW_AUTHENTICATE_SCHEME,
            ]
        );
    }

    private function hasBearerScheme(string $header): bool
    {
        return str_starts_with(strtolower($header), strtolower(self::BEARER_PREFIX));
    }

    private function extractPlainToken(string $header): string
    {
        return trim(substr($header, strlen(self::BEARER_PREFIX)));
    }

    private function updateLastUsedAtThrottled(AbstractPersonalAccessToken $personalAccessToken): void
    {
        if (AnzuApp::isReadOnlyMode()) {
            return;
        }
        $lastUsedAt = $personalAccessToken->getLastUsedAt();
        $throttleThreshold = AnzuApp::date(sprintf('-%d seconds', PersonalAccessTokenAuthCache::USER_ID_EXPIRE_TIME));
        if ($lastUsedAt instanceof DateTimeImmutable && $lastUsedAt > $throttleThreshold) {
            return;
        }
        $this->personalAccessTokenManager->updateLastUsedAt($personalAccessToken);
    }
}

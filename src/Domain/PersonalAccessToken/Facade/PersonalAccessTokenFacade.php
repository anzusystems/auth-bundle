<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Facade;

use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Cache\PersonalAccessTokenAuthCache;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Manager\PersonalAccessTokenManager;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Model\PersonalAccessTokenCreateResult;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Repository\PersonalAccessTokenRepository;
use AnzuSystems\AuthBundle\Entity\AbstractPersonalAccessToken;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Validator\Validator;
use AnzuSystems\Contracts\Entity\AnzuUser;
use DateTimeImmutable;
use Random\RandomException;

final readonly class PersonalAccessTokenFacade
{
    public const int TOKEN_BYTES_LENGTH = 32;

    /**
     * @param class-string<AbstractPersonalAccessToken> $entityClass
     */
    public function __construct(
        private Validator $validator,
        private PersonalAccessTokenManager $manager,
        private PersonalAccessTokenRepository $repository,
        private PersonalAccessTokenAuthCache $authCache,
        private string $entityClass,
    ) {
    }

    /**
     * @throws ValidationException
     * @throws RandomException
     */
    public function create(
        AnzuUser $user,
        string $name,
        ?DateTimeImmutable $expiresAt = null,
        ?int $rateLimit = null,
        bool $neverExpires = false,
    ): PersonalAccessTokenCreateResult {
        if ($neverExpires && $expiresAt instanceof DateTimeImmutable) {
            throw new ValidationException()->addFormattedError('expiresAt', ValidationException::ERROR_FIELD_INVALID);
        }
        $plainToken = AbstractPersonalAccessToken::TOKEN_PREFIX . bin2hex(random_bytes(self::TOKEN_BYTES_LENGTH));
        $personalAccessToken = new $this->entityClass();
        $personalAccessToken
            ->setUser($user)
            ->setName($name)
            ->setTokenHash(AbstractPersonalAccessToken::hashToken($plainToken))
            ->setRateLimit($rateLimit)
        ;
        if ($expiresAt instanceof DateTimeImmutable) {
            $personalAccessToken->setExpiresAt($expiresAt);
        }
        if ($neverExpires) {
            $personalAccessToken->setExpiresAt(null);
        }
        $this->validator->validate($personalAccessToken);
        $this->manager->create($personalAccessToken);

        return new PersonalAccessTokenCreateResult($plainToken, $personalAccessToken);
    }

    public function deleteByUser(AnzuUser $user): void
    {
        $personalAccessTokens = $this->repository->findByUser($user);
        foreach ($personalAccessTokens as $personalAccessToken) {
            $this->manager->delete($personalAccessToken, false);
        }
        $this->manager->flush();
        foreach ($personalAccessTokens as $personalAccessToken) {
            $this->authCache->invalidate($personalAccessToken->getTokenHash());
        }
    }

    public function revoke(AbstractPersonalAccessToken $personalAccessToken): AbstractPersonalAccessToken
    {
        if ($personalAccessToken->isRevoked()) {
            return $personalAccessToken;
        }

        $revoked = $this->manager->revoke($personalAccessToken);
        $this->authCache->invalidate($revoked->getTokenHash());

        return $revoked;
    }
}

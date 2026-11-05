<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Facade;

use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Manager\PersonalAccessTokenManager;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Model\PersonalAccessTokenCreateResult;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Repository\PersonalAccessTokenRepository;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Resolver\PersonalAccessTokenToolResolver;
use AnzuSystems\AuthBundle\Entity\AbstractPersonalAccessToken;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Validator\Validator;
use AnzuSystems\Contracts\Entity\AnzuUser;
use DateTimeImmutable;
use Random\RandomException;

final readonly class PersonalAccessTokenFacade
{
    public const int TOKEN_BYTES_LENGTH = 32;

    private const string FIELD_READ_TOOLS = 'readTools';
    private const string FIELD_WRITE_TOOLS = 'writeTools';

    /**
     * @param class-string<AbstractPersonalAccessToken> $entityClass
     */
    public function __construct(
        private Validator $validator,
        private PersonalAccessTokenManager $manager,
        private PersonalAccessTokenRepository $repository,
        private PersonalAccessTokenToolResolver $toolResolver,
        private string $entityClass,
    ) {
    }

    /**
     * Creates a token for a user acting on their own, so it can hold only the tools the user may use. The console trusts
     * its operator and calls create() directly.
     *
     * @param array<string> $writeTools
     * @param array<string>|null $readTools
     *
     * @throws ValidationException
     * @throws RandomException
     */
    public function createGranted(
        AnzuUser $user,
        string $name,
        ?DateTimeImmutable $expiresAt = null,
        array $writeTools = [],
        ?array $readTools = null,
    ): PersonalAccessTokenCreateResult {
        $grantableToolNames = $this->toolResolver->resolveGrantableToolNames();
        $exception = new ValidationException();
        if ($this->hasUngrantableTool($readTools ?? [], $grantableToolNames)) {
            $exception->addFormattedError(self::FIELD_READ_TOOLS, ValidationException::ERROR_FIELD_INVALID);
        }
        if ($this->hasUngrantableTool($writeTools, $grantableToolNames)) {
            $exception->addFormattedError(self::FIELD_WRITE_TOOLS, ValidationException::ERROR_FIELD_INVALID);
        }
        if (false === empty($exception->getFormattedErrors())) {
            throw $exception;
        }

        return $this->create(
            user: $user,
            name: $name,
            expiresAt: $expiresAt,
            writeTools: $writeTools,
            readTools: $readTools,
        );
    }

    /**
     * @param array<string> $writeTools
     * @param array<string>|null $readTools
     *
     * @throws ValidationException
     * @throws RandomException
     */
    public function create(
        AnzuUser $user,
        string $name,
        ?DateTimeImmutable $expiresAt = null,
        bool $neverExpires = false,
        array $writeTools = [],
        ?array $readTools = null,
    ): PersonalAccessTokenCreateResult {
        if ($neverExpires && $expiresAt instanceof DateTimeImmutable) {
            throw new ValidationException()
                ->addFormattedError('expiresAt', ValidationException::ERROR_FIELD_INVALID);
        }
        $plainToken = AbstractPersonalAccessToken::TOKEN_PREFIX . bin2hex(random_bytes(self::TOKEN_BYTES_LENGTH));
        $personalAccessToken = new $this->entityClass();
        $personalAccessToken
            ->setUser($user)
            ->setName($name)
            ->setTokenHash(AbstractPersonalAccessToken::hashToken($plainToken))
            ->setWriteTools($writeTools)
            ->setReadTools($readTools)
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
    }

    public function revoke(AbstractPersonalAccessToken $personalAccessToken): AbstractPersonalAccessToken
    {
        if ($personalAccessToken->isRevoked()) {
            return $personalAccessToken;
        }

        return $this->manager->revoke($personalAccessToken);
    }

    /**
     * @param array<mixed> $tools
     * @param list<string> $grantableToolNames
     */
    private function hasUngrantableTool(array $tools, array $grantableToolNames): bool
    {
        return array_any(
            $tools,
            static fn (mixed $tool): bool => false === in_array(needle: $tool, haystack: $grantableToolNames, strict: true),
        );
    }
}

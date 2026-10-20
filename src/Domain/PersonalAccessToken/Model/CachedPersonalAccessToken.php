<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Model;

use AnzuSystems\AuthBundle\Entity\AbstractPersonalAccessToken;

final readonly class CachedPersonalAccessToken
{
    /**
     * @param list<string> $writeTools
     */
    public function __construct(
        public int $personalAccessTokenId,
        public int $userId,
        public array $writeTools,
    ) {
    }

    public static function fromEntity(AbstractPersonalAccessToken $personalAccessToken): self
    {
        return new self(
            personalAccessTokenId: (int) $personalAccessToken->getId(),
            userId: (int) $personalAccessToken->getUser()
                ->getId(),
            writeTools: $personalAccessToken->getWriteTools(),
        );
    }
}

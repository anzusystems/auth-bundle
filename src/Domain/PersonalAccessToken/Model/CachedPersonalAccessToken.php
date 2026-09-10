<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Model;

use AnzuSystems\AuthBundle\Entity\AbstractPersonalAccessToken;

final readonly class CachedPersonalAccessToken
{
    public function __construct(
        public int $personalAccessTokenId,
        public int $userId,
    ) {
    }

    public static function fromEntity(AbstractPersonalAccessToken $personalAccessToken): self
    {
        return new self(
            (int) $personalAccessToken->getId(),
            (int) $personalAccessToken->getUser()
                ->getId(),
        );
    }
}

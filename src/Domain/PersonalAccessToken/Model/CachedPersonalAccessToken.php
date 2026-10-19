<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Model;

use AnzuSystems\AuthBundle\Entity\AbstractPersonalAccessToken;
use AnzuSystems\AuthBundle\Model\Enum\PersonalAccessTokenScope;

final readonly class CachedPersonalAccessToken
{
    public function __construct(
        public int $personalAccessTokenId,
        public int $userId,
        public PersonalAccessTokenScope $scope,
    ) {
    }

    public static function fromEntity(AbstractPersonalAccessToken $personalAccessToken): self
    {
        return new self(
            personalAccessTokenId: (int) $personalAccessToken->getId(),
            userId: (int) $personalAccessToken->getUser()
                ->getId(),
            scope: $personalAccessToken->getScope(),
        );
    }
}

<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Model;

use AnzuSystems\AuthBundle\Entity\AbstractPersonalAccessToken;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final readonly class PersonalAccessTokenCreateResult
{
    public function __construct(
        #[Serialize]
        public string $token,
        #[Serialize]
        public AbstractPersonalAccessToken $personalAccessToken,
    ) {
    }
}

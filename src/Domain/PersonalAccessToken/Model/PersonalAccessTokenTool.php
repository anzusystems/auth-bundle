<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Model;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final readonly class PersonalAccessTokenTool
{
    public function __construct(
        #[Serialize]
        public string $name,
        #[Serialize]
        public bool $writeTool,
    ) {
    }
}

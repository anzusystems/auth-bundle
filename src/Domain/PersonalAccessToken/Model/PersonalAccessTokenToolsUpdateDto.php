<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Model;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class PersonalAccessTokenToolsUpdateDto
{
    /**
     * @var list<string>|null
     */
    #[Serialize]
    public ?array $readTools = null;

    /**
     * @var list<string>
     */
    #[Serialize]
    public array $explicitReadTools = [];

    /**
     * @var list<string>|null
     */
    #[Serialize]
    public ?array $writeTools = null;
}

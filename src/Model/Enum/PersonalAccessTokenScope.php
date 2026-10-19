<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum PersonalAccessTokenScope: string implements EnumInterface
{
    use BaseEnumTrait;

    case Read = 'read';
    case ReadWrite = 'readWrite';

    public const Default = self::Read;
}

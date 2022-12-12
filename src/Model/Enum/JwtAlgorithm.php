<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;
use Lcobucci\JWT\Signer;

enum JwtAlgorithm: string implements EnumInterface
{
    use BaseEnumTrait;

    case ES256 = 'ES256';
    case RS256 = 'RS256';

    public const Default = self::ES256;

    public function signer(): Signer\Ecdsa | Signer\Rsa\Sha256
    {
        return match ($this) {
            self::ES256 => Signer\Ecdsa\Sha256::create(),
            self::RS256 => new Signer\Rsa\Sha256(),
        };
    }
}

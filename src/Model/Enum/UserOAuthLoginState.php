<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum UserOAuthLoginState: string implements EnumInterface
{
    use BaseEnumTrait;

    case Success = 'success';
    case FailureSsoCommunicationFailed = 'failure-sso-communication';
    case FailureInternalError = 'failure-internal-error';
    case FailureUnauthorized = 'failure-unauthorized';

    public const Default = self::Success;
}

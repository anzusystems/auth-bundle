<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Exception;

use AnzuSystems\Contracts\Exception\AnzuException;

final class InvalidRefreshTokenException extends AnzuException
{
    public static function create(): self
    {
        return new self('Invalid refresh token on request');
    }
}

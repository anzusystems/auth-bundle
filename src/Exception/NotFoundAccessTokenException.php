<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Exception;

use AnzuSystems\Contracts\Exception\AnzuException;

final class NotFoundAccessTokenException extends AnzuException
{
    public static function create(): self
    {
        return new self('Access couldn\'t be fetched from the storage!');
    }
}

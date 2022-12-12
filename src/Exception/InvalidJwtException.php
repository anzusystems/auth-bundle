<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Exception;

use AnzuSystems\Contracts\Exception\AnzuException;
use Throwable;

final class InvalidJwtException extends AnzuException
{
    public static function create(string $jwt, ?Throwable $previous = null): self
    {
        return new self(sprintf('Provided "%s" is not valid JWT token.', $jwt), 0, $previous);
    }
}

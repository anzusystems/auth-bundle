<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Exception;

use AnzuSystems\Contracts\Exception\AnzuException;
use Throwable;

final class UnsuccessfulOAuth2RequestException extends AnzuException
{
    public static function create(string $message, ?Throwable $previous = null): self
    {
        return new self($message, 0, $previous);
    }
}

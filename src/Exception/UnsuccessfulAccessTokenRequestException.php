<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Exception;

use AnzuSystems\Contracts\Exception\AnzuException;
use Throwable;

final class UnsuccessfulAccessTokenRequestException extends AnzuException
{
    private array $bodyParams = [];

    public static function create(string $message, ?Throwable $previous = null): self
    {
        return new self($message, 0, $previous);
    }

    public function setBodyParams(array $bodyParams): self
    {
        $this->bodyParams = $bodyParams;

        return $this;
    }

    public function getBodyParams(): array
    {
        return $this->bodyParams;
    }
}

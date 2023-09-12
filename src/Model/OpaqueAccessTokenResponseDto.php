<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Model;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class OpaqueAccessTokenResponseDto
{
    #[Serialize(serializedName: 'access_token')]
    private string $accessToken;

    #[Serialize(serializedName: 'expires_in')]
    private int $expiresIn;

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function setAccessToken(string $accessToken): self
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    public function getExpiresIn(): int
    {
        return $this->expiresIn;
    }

    public function setExpiresIn(int $expiresIn): self
    {
        $this->expiresIn = $expiresIn;

        return $this;
    }
}

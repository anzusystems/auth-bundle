<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Model;

use AnzuSystems\AuthBundle\Serializer\Handler\Handlers\JwtHandler;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Lcobucci\JWT\Token\Plain;

final class AccessTokenResponseDto
{
    #[Serialize(serializedName: 'access_token', handler: JwtHandler::class)]
    private Plain $accessToken;

    public function getAccessToken(): Plain
    {
        return $this->accessToken;
    }

    public function setAccessToken(Plain $accessToken): self
    {
        $this->accessToken = $accessToken;

        return $this;
    }
}

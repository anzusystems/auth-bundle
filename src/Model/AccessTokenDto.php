<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Model;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Lcobucci\JWT\Token\Plain;

final class AccessTokenDto
{
    private ?Plain $jwt;
    private string $accessToken;
    private DateTimeInterface $expiresAt;

    public function __construct(string $accessToken, DateTimeInterface $expiresAt, ?Plain $accessTokenJwt = null)
    {
        $this->accessToken = $accessToken;
        $this->expiresAt = $expiresAt;
        $this->jwt = $accessTokenJwt;
    }

    public function getJwt(): ?Plain
    {
        return $this->jwt;
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function getExpiresAt(): DateTimeInterface
    {
        return $this->expiresAt;
    }

    public static function createFromJwtAccessTokenResponse(AccessTokenResponseDto $accessTokenResponseDto): self
    {
        $jwt = $accessTokenResponseDto->getAccessToken();
        /** @var DateTimeInterface $expiresAt */
        $expiresAt = $jwt->claims()->get('exp');

        return new self($jwt->toString(), $expiresAt, $jwt);
    }

    public static function createFromOpaqueAccessTokenResponse(OpaqueAccessTokenResponseDto $accessTokenResponseDto): self
    {
        $date = (new DateTimeImmutable())->add(new DateInterval('PT' . $accessTokenResponseDto->getExpiresIn() . 'S'));

        return new self($accessTokenResponseDto->getAccessToken(), $date);
    }
}

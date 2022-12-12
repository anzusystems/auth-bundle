<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Model;

use AnzuSystems\AuthBundle\Exception\InvalidJwtException;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use Throwable;

final class OAuth2TokenResponseDto
{
    private Plain $accessToken;
    private string $refreshToken;

    /**
     * @throws InvalidJwtException
     */
    public function __construct(string $accessToken, string $refreshToken)
    {
        try {
            /** @var Plain $token */
            $token = (new Parser(new JoseEncoder()))->parse($accessToken);
            $this->accessToken = $token;
            $this->refreshToken = $refreshToken;
        } catch (Throwable $exception) {
            throw InvalidJwtException::create($accessToken, $exception);
        }
    }

    /**
     * @param string[] $data
     *
     * @throws InvalidJwtException
     */
    public static function createFromArray(array $data): self
    {
        return new self($data['access_token'], $data['refresh_token']);
    }

    public function getAccessToken(): Plain
    {
        return $this->accessToken;
    }

    public function getRefreshToken(): string
    {
        return $this->refreshToken;
    }
}

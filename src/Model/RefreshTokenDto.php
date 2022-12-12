<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Model;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use DateTimeImmutable;
use Stringable;

final class RefreshTokenDto implements Stringable
{
    public const REFRESH_TOKEN_SEPARATOR = ':';

    #[Serialize]
    private string $tokenHashPlain;

    #[Serialize]
    private string $userId;

    #[Serialize]
    private DeviceDto $device;

    #[Serialize]
    private DateTimeImmutable $expiresAt;

    #[Serialize]
    private DateTimeImmutable $issuedAt;

    public function __construct()
    {
        $this->tokenHashPlain = $tokenHashPlain ?? bin2hex(random_bytes(32));
        $this->issuedAt = new DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->getUserId() . self::REFRESH_TOKEN_SEPARATOR . $this->getTokenHashPlain();
    }

    public static function create(string $userId, DeviceDto $device, DateTimeImmutable $expiresAt): self
    {
        return (new self())
            ->setUserId($userId)
            ->setDevice($device)
            ->setExpiresAt($expiresAt)
        ;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function getTokenHashPlain(): string
    {
        return $this->tokenHashPlain;
    }

    public function setTokenHashPlain(string $tokenHashPlain): self
    {
        $this->tokenHashPlain = $tokenHashPlain;

        return $this;
    }

    public function getDevice(): DeviceDto
    {
        return $this->device;
    }

    public function setDevice(DeviceDto $device): self
    {
        $this->device = $device;

        return $this;
    }

    public function getIssuedAt(): DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function setIssuedAt(DateTimeImmutable $issuedAt): self
    {
        $this->issuedAt = $issuedAt;

        return $this;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function toString(): string
    {
        return (string) $this;
    }
}

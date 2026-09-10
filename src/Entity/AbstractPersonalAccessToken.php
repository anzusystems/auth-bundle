<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Entity;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\Contracts\Entity\Interfaces\IdentifiableInterface;
use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\Contracts\Entity\Traits\IdentityIntTrait;
use AnzuSystems\Contracts\Entity\Traits\TimeTrackingTrait;
use AnzuSystems\Contracts\Entity\Traits\UserTrackingTrait;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use LogicException;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\MappedSuperclass]
abstract class AbstractPersonalAccessToken implements IdentifiableInterface, TimeTrackingInterface, UserTrackingInterface
{
    use IdentityIntTrait;
    use TimeTrackingTrait;
    use UserTrackingTrait;

    public const string TOKEN_PREFIX = 'anzu_pat_';
    public const int TOKEN_HASH_LENGTH = 64;
    public const string DEFAULT_EXPIRES_AT_DATE = '+2 months';
    public const string MAX_EXPIRES_AT_DATE = '+1 year';

    #[ORM\ManyToOne(targetEntity: AnzuUser::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Serialize(handler: EntityIdHandler::class)]
    #[Assert\NotNull(message: ValidationException::ERROR_FIELD_EMPTY)]
    protected ?AnzuUser $user = null;

    #[ORM\Column(type: Types::STRING, length: 255, options: ['default' => ''])]
    #[Serialize]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Assert\Length(max: 255, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    protected string $name = '';

    #[ORM\Column(type: Types::STRING, length: self::TOKEN_HASH_LENGTH)]
    protected string $tokenHash = '';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Serialize]
    #[Assert\GreaterThan(value: 'now', message: ValidationException::ERROR_FIELD_RANGE_MIN)]
    #[Assert\LessThanOrEqual(value: self::MAX_EXPIRES_AT_DATE, message: ValidationException::ERROR_FIELD_RANGE_MAX)]
    protected ?DateTimeImmutable $expiresAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Serialize]
    protected ?DateTimeImmutable $revokedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Serialize]
    protected ?DateTimeImmutable $lastUsedAt = null;

    final public function __construct()
    {
        $this->expiresAt = AnzuApp::date(self::DEFAULT_EXPIRES_AT_DATE);
    }

    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt instanceof DateTimeImmutable;
    }

    public function getUser(): AnzuUser
    {
        if (null === $this->user) {
            throw new LogicException('Personal access token user is not set.');
        }

        return $this->user;
    }

    public function setUser(AnzuUser $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function setTokenHash(string $tokenHash): static
    {
        $this->tokenHash = $tokenHash;

        return $this;
    }

    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getRevokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function setRevokedAt(?DateTimeImmutable $revokedAt): static
    {
        $this->revokedAt = $revokedAt;

        return $this;
    }

    public function getLastUsedAt(): ?DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(?DateTimeImmutable $lastUsedAt): static
    {
        $this->lastUsedAt = $lastUsedAt;

        return $this;
    }
}

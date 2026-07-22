<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Model;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use DateTimeImmutable;

final class PersonalAccessTokenCreateDto
{
    #[Serialize]
    private string $name = '';

    #[Serialize]
    private ?DateTimeImmutable $expiresAt = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }
}

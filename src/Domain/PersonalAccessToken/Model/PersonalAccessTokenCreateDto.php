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

    /**
     * @var list<string>|null
     */
    #[Serialize]
    private ?array $writeTools = null;

    /**
     * @var list<string>|null
     */
    #[Serialize]
    private ?array $readTools = null;

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

    /**
     * @return list<string>
     */
    public function getWriteTools(): array
    {
        return $this->writeTools ?? [];
    }

    /**
     * @param list<string>|null $writeTools
     */
    public function setWriteTools(?array $writeTools): self
    {
        $this->writeTools = $writeTools;

        return $this;
    }

    /**
     * @return list<string>|null
     */
    public function getReadTools(): ?array
    {
        return $this->readTools;
    }

    /**
     * @param list<string>|null $readTools
     */
    public function setReadTools(?array $readTools): self
    {
        $this->readTools = $readTools;

        return $this;
    }
}

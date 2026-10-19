<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Model;

use AnzuSystems\AuthBundle\Model\Enum\PersonalAccessTokenScope;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use DateTimeImmutable;

final class PersonalAccessTokenCreateDto
{
    #[Serialize]
    private string $name = '';

    #[Serialize]
    private ?DateTimeImmutable $expiresAt = null;

    #[Serialize]
    private PersonalAccessTokenScope $scope = PersonalAccessTokenScope::Default;

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

    public function getScope(): PersonalAccessTokenScope
    {
        return $this->scope;
    }

    public function setScope(PersonalAccessTokenScope $scope): self
    {
        $this->scope = $scope;

        return $this;
    }
}

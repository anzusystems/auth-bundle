<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Model;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

/**
 * @psalm-suppress ClassMustBeFinal
 */
class SsoUserDto
{
    #[Serialize]
    protected string $id = '';

    #[Serialize]
    protected string $email = '';

    public function getId(): string
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }
}

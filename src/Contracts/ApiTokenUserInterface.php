<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Contracts;

interface ApiTokenUserInterface
{
    public function getApiToken(): ?string;

    public function setApiToken(?string $apiToken): static;
}

<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Contracts;

use AnzuSystems\Contracts\Entity\Interfaces\EnableInterface;
use Symfony\Component\Security\Core\User\UserInterface;

interface AnzuAuthUserInterface extends UserInterface, EnableInterface
{
    public function getAuthId(): string;
}

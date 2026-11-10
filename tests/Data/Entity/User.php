<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Data\Entity;

use AnzuSystems\AuthBundle\Contracts\AnzuAuthUserInterface;
use AnzuSystems\AuthBundle\Tests\Data\Repository\UserRepository;
use AnzuSystems\Contracts\Entity\AnzuUser;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
class User extends AnzuUser implements AnzuAuthUserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * @var Collection<int, PermissionGroup>
     */
    #[ORM\ManyToMany(targetEntity: PermissionGroup::class, inversedBy: 'users', fetch: 'EXTRA_LAZY', indexBy: 'id')]
    #[ORM\JoinTable]
    protected Collection $permissionGroups;

    #[ORM\Column(type: Types::STRING)]
    private string $password = '';

    public function getAuthId(): string
    {
        return $this->getUserIdentifier();
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }
}

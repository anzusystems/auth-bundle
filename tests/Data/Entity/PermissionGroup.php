<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Data\Entity;

use AnzuSystems\Contracts\Entity\AnzuPermissionGroup;
use AnzuSystems\Contracts\Entity\Traits\UserTrackingTrait;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class PermissionGroup extends AnzuPermissionGroup
{
    use UserTrackingTrait;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'permissionGroups', indexBy: 'id')]
    protected Collection $users;
}

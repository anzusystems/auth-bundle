<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Data\Entity;

use AnzuSystems\AuthBundle\Entity\AbstractPersonalAccessToken;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
final class PersonalAccessToken extends AbstractPersonalAccessToken
{
}

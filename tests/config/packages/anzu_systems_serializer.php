<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use AnzuSystems\AuthBundle\Tests\Data\Entity\User;
use AnzuSystems\Contracts\Entity\AnzuUser;

return [
    'anzu_systems_serializer' => [
        'parameter_bag' => [
            AnzuUser::class => User::class,
        ],
    ],
];

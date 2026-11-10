<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use AnzuSystems\AuthBundle\Tests\Data\Entity\User;
use AnzuSystems\Contracts\Entity\AnzuUser;

return [
    'doctrine' => [
        'dbal' => [
            'url' => env('DB_AUTHBUNDLE_URL')->resolve(),
        ],
        'orm' => [
            'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
            'auto_mapping' => false,
            'mappings' => [
                'App' => [
                    'dir' => '%kernel.project_dir%/tests/Data/Entity',
                    'prefix' => 'AnzuSystems\AuthBundle\Tests\Data\Entity',
                    'type' => 'attribute',
                ],
                'AnzuSystemsAuthBundle' => [
                    'dir' => '%kernel.project_dir%/src/Entity',
                    'prefix' => 'AnzuSystems\AuthBundle\Entity',
                    'type' => 'attribute',
                ],
                'AnzuSystemsContractsEmbeds' => [
                    'dir' => '%kernel.project_dir%/vendor/anzusystems/contracts/src/Entity/Embeds',
                    'prefix' => 'AnzuSystems\Contracts\Entity\Embeds',
                    'type' => 'attribute',
                ],
            ],
            'resolve_target_entities' => [
                AnzuUser::class => User::class,
            ],
        ],
    ],
];

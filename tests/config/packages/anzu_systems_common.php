<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use AnzuSystems\AuthBundle\Tests\Data\Entity\User;
use AnzuSystems\CommonBundle\Command\GenerateFixturesCommand;
use Doctrine\ORM\Tools\Console\Command\SchemaTool\CreateCommand;

return [
    'anzu_systems_common' => [
        'settings' => [
            'app_redis' => 'TestRedis',
            'user_entity_class' => User::class,
            'app_entity_namespace' => 'AnzuSystems\AuthBundle\Tests\Data\Entity',
            // A locked command takes its lock in Redis, which does not run next to the tests.
            'unlocked_commands' => [
                CreateCommand::class,
                GenerateFixturesCommand::class,
            ],
        ],
        'health_check' => [
            'enabled' => false,
        ],
        // No MongoDB runs next to the tests: the clients connect lazily and the log messages stay in the in-memory transport.
        'logs' => [
            'messenger_transport' => [
                'name' => 'common_bundle_log',
                'dsn' => 'in-memory://',
            ],
            'journal' => [
                'mongo' => [
                    'uri' => 'mongodb://localhost:27017',
                    'username' => 'example',
                    'password' => 'example',
                    'database' => 'authbundle',
                ],
            ],
            'audit' => [
                'mongo' => [
                    'uri' => 'mongodb://localhost:27017',
                    'username' => 'example',
                    'password' => 'example',
                    'database' => 'authbundle',
                ],
            ],
        ],
    ],
];

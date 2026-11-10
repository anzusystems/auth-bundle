<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use AnzuSystems\AuthBundle\Security\Authentication\JwtAuthentication;
use AnzuSystems\AuthBundle\Security\Authentication\PersonalAccessTokenAuthenticator;
use AnzuSystems\AuthBundle\Tests\Data\Entity\User;

return [
    'security' => [
        'providers' => [
            'user_by_id' => [
                'entity' => [
                    'class' => User::class,
                    'property' => 'id',
                ],
            ],
        ],
        'firewalls' => [
            'auth' => [
                'pattern' => '^/auth/',
                'security' => false,
            ],
            'token_api' => [
                'pattern' => '^/token-api/',
                'stateless' => true,
                'provider' => 'user_by_id',
                'custom_authenticators' => [PersonalAccessTokenAuthenticator::class],
            ],
            'main' => [
                'stateless' => true,
                'provider' => 'user_by_id',
                'custom_authenticators' => [JwtAuthentication::class],
            ],
        ],
    ],
];

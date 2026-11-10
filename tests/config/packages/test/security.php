<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use AnzuSystems\AuthBundle\Security\Authentication\JwtAuthentication;
use AnzuSystems\AuthBundle\Security\Authentication\PersonalAccessTokenAuthenticator;
use AnzuSystems\AuthBundle\Security\AuthenticationFailureHandler;
use AnzuSystems\AuthBundle\Security\AuthenticationSuccessHandler;
use AnzuSystems\AuthBundle\Tests\Data\Entity\User;

return [
    'security' => [
        'password_hashers' => [
            User::class => 'plaintext',
        ],
        'providers' => [
            'user_by_id' => [
                'entity' => [
                    'class' => User::class,
                    'property' => 'id',
                ],
            ],
            'user_by_email' => [
                'entity' => [
                    'class' => User::class,
                    'property' => 'email',
                ],
            ],
        ],
        'firewalls' => [
            'auth' => [
                'pattern' => '^/auth/',
                'stateless' => true,
                'provider' => 'user_by_email',
                'json_login' => [
                    'check_path' => 'auth_login',
                    'success_handler' => AuthenticationSuccessHandler::class,
                    'failure_handler' => AuthenticationFailureHandler::class,
                ],
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

<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use AnzuSystems\AuthBundle\Tests\Data\RefreshTokenStorage\InMemoryRefreshTokenStorage;
use AnzuSystems\AuthBundle\Tests\Data\Repository\UserRepository;

return [
    'anzu_systems_auth' => [
        'authorization' => [
            'type' => 'oauth2',
            'enabled' => true,
            'refresh_token' => [
                'storage' => [
                    'custom' => [
                        'service_id' => InMemoryRefreshTokenStorage::class,
                    ],
                ],
            ],
            'auth_redirect_default_url' => 'http://localhost/default-redirect',
            'auth_redirect_query_url_allowed_pattern' => '^http://localhost/allowed/',
            'oauth2' => [
                'user_repository_service_id' => UserRepository::class,
                'state_token_salt' => 'example-salt',
                'authorize_url' => 'https://sso.example.com/authorize',
                'access_token_url' => 'https://sso.example.com/token',
                'redirect_url' => 'http://localhost/auth/authorize',
                // The tests sign the provider's access token with the application key pair, so both share the audience.
                'client_id' => 'example-audience',
                'client_secret' => 'example-secret',
                'public_cert' => env('AUTH_JWT_PUBLIC_CERT')->base64(),
                'scopes' => ['openid', 'email'],
                'scope_delimiter' => ' ',
            ],
        ],
    ],
];

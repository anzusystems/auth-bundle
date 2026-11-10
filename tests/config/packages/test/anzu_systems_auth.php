<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use AnzuSystems\AuthBundle\Tests\Data\RefreshTokenStorage\InMemoryRefreshTokenStorage;

return [
    'anzu_systems_auth' => [
        'authorization' => [
            'type' => 'json_credentials',
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
        ],
    ],
];

<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use AnzuSystems\AuthBundle\Tests\Data\Entity\PersonalAccessToken;

return [
    'anzu_systems_auth' => [
        'cookie' => [
            'secure' => false,
        ],
        'jwt' => [
            'audience' => 'example-audience',
            'public_cert' => env('AUTH_JWT_PUBLIC_CERT')->base64(),
            'private_cert' => env('AUTH_JWT_PRIVATE_CERT')->base64(),
        ],
        'personal_access_token' => [
            'enabled' => true,
            'entity_class' => PersonalAccessToken::class,
        ],
    ],
];

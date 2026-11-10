<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return [
    'framework' => [
        'test' => true,
        'secret' => env('APP_SECRET'),
        'session' => [
            'enabled' => false,
        ],
        'router' => [
            'utf8' => true,
        ],
        'http_method_override' => false,
    ],
];

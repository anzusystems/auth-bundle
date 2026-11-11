<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return [
    'monolog' => [
        'handlers' => [
            // Keeps the records in memory, LogAssertionsTrait reads them.
            'main' => [
                'type' => 'test',
            ],
        ],
    ],
];

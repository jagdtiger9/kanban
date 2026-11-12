<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\App;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;

return App::config([
    'doctrine' => [
        'dbal' => [
            'url' => env('DATABASE_URL')->resolve(),
            'driver' => 'pdo_pgsql',
            'charset' => 'utf8',
            'profiling_collect_backtrace' => '%kernel.debug%',
        ],
        'orm' => [
            'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
            'auto_mapping' => true,
            'mappings' => [
                // Per-module entity mappings are added here as modules land
                // (same convention as the shop project).
            ],
        ],
    ],
]);

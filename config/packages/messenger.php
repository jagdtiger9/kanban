<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\App;

return App::config([
    'framework' => [
        'messenger' => [
            'default_bus' => 'command.bus',
            'buses' => [
                'query.bus' => [],
                'command.bus' => [
                    'middleware' => [
                        ['id' => 'doctrine_ping_connection'],
                        ['id' => 'doctrine_close_connection'],
                        ['id' => 'doctrine_transaction'],
                    ],
                ],
            ],
        ],
    ],
]);

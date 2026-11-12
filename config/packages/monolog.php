<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\App;

return App::config([
    'monolog' => [
        'handlers' => [
            'main' => [
                'type' => 'stream',
                'path' => '%kernel.logs_dir%/%kernel.environment%.log',
                'level' => 'debug',
                'channels' => ['!event'],
            ],
            'console' => [
                'type' => 'console',
                'process_psr_3_messages' => false,
                'channels' => ['!event', '!doctrine', '!console'],
            ],
        ],
    ],
]);

<?php

# Pre-migration config/packages/mailer.yaml (flex-generated), kept for reference:
#
# framework:
#     mailer:
#         dsn: '%env(MAILER_DSN)%'

use Symfony\Component\DependencyInjection\Loader\Configurator\App;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;

return App::config([
    'framework' => [
        'mailer' => [
            'dsn' => env('MAILER_DSN'),
            'message_bus' => false,
            'envelope' => [
                'sender' => 'noreply@kanban.local',
            ],
        ],
    ],
]);

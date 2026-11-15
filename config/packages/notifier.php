<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\App;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;

return App::config([
    'framework' => [
        'notifier' => [
            // Notifications are dispatched as Messenger messages and routed to
            // the async transport (see messenger.php), so a failed delivery is
            // retried on its own instead of replaying the source domain event.
            // Spelled out because FrameworkBundle turns `true` into a reference
            // to a service named "1" when compiling the chatter service.
            'message_bus' => 'messenger.default_bus',
            'chatter_transports' => [
                'telegram' => env('TELEGRAM_DSN'),
            ],
        ],
    ],
]);

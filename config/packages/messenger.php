<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\App;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;

return App::config([
    'framework' => [
        'messenger' => [
            'default_bus' => 'command.bus',
            'failure_transport' => 'failed',
            'buses' => [
                'query.bus' => [],
                'command.bus' => [
                    'middleware' => [
                        ['id' => 'doctrine_ping_connection'],
                        ['id' => 'doctrine_close_connection'],
                        ['id' => 'doctrine_transaction'],
                    ],
                ],
                'event.bus' => [
                    'default_middleware' => [
                        'allow_no_handlers' => true,
                    ],
                    'middleware' => [
                        ['id' => 'doctrine_ping_connection'],
                        ['id' => 'doctrine_close_connection'],
                        ['id' => 'doctrine_transaction'],
                    ],
                ],
            ],
            'transports' => [
                'sync' => [
                    'dsn' => 'sync://',
                ],
                // Failed messages land in the doctrine transport's table
                // (messenger_messages), which the ORM schema is filtered to ignore.
                'failed' => [
                    'dsn' => 'doctrine://default?queue_name=failed',
                ],
                'async' => [
                    'dsn' => env('MESSENGER_TRANSPORT_DSN'),
                    'options' => [
                        'auto_setup' => true,
                        'queues' => [
                            'kanban_default' => [
                                'binding_keys' => ['kanban_default'],
                            ],
                        ],
                        'exchange' => [
                            'name' => 'kanban',
                            'type' => 'direct',
                            'default_publish_routing_key' => 'kanban_default',
                        ],
                    ],
                    'retry_strategy' => [
                        'delay' => 1000,
                        'multiplier' => 2,
                        'max_delay' => 10000,
                        'max_retries' => 3,
                    ],
                ],
            ],
            'routing' => [
                'App\Board\Domain\Event\*' => ['async'],
                'App\News\Domain\Event\*' => ['async'],
                'App\Project\Domain\Event\*' => ['async'],
                'App\User\Domain\Event\*' => ['async'],
                // Deferred by Symfony Notifier's chatter; retried on its own.
                'Symfony\Component\Notifier\Message\ChatMessage' => ['async'],
            ],
        ],
    ],
]);

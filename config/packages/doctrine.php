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
            // The Doctrine Messenger transport owns this table; keep it out of
            // ORM schema diffs, which would otherwise try to drop it.
            'schema_filter' => '~^(?!messenger_messages)~',
            // ramsey/uuid-doctrine ships only DBAL types (no Symfony bundle) —
            // the uuid type must be registered here manually.
            'types' => [
                'uuid' => 'Ramsey\Uuid\Doctrine\UuidType',
                'datetimetz_micro' => 'App\Shared\Infrastructure\Doctrine\Type\DatetimeTzMicroType',
            ],
        ],
        'orm' => [
            'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
            'auto_mapping' => true,
            'mappings' => [
                'App\\User' => [
                    'type' => 'attribute',
                    'dir' => '%kernel.project_dir%/src/User/Domain/Entity',
                    'prefix' => 'App\\User\\Domain\\Entity',
                    'is_bundle' => false,
                ],
                'App\\Project' => [
                    'type' => 'attribute',
                    'dir' => '%kernel.project_dir%/src/Project/Domain/Entity',
                    'prefix' => 'App\\Project\\Domain\\Entity',
                    'is_bundle' => false,
                ],
                'App\\Board' => [
                    'type' => 'attribute',
                    'dir' => '%kernel.project_dir%/src/Board/Domain/Entity',
                    'prefix' => 'App\\Board\\Domain\\Entity',
                    'is_bundle' => false,
                ],
                'App\\Shared' => [
                    'type' => 'attribute',
                    'dir' => '%kernel.project_dir%/src/Shared/Domain/Entity',
                    'prefix' => 'App\\Shared\\Domain\\Entity',
                    'is_bundle' => false,
                ],
            ],
        ],
    ],
]);

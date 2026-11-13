<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Redis;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\RedisSessionHandler;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

# This file is the entry point to configure your own services.
# Files in the packages/ subdirectory configure your dependencies.

return App::config([
    'parameters' => [
        'security.remember_me.name' => 'remember_me',
        'security.login_check.url' => 'login_check',
        'frontend_url' => env('FRONTEND_URL'),
    ],
    'services' => [
        // default configuration for services in *this* file
        '_defaults' => [
            'autowire' => true,
            'autoconfigure' => true,
        ],
        // makes classes in src/ available to be used as services
        // this creates a service per class whose id is the fully-qualified class name
        'App\\' => ['resource' => '../src/'],

        Redis::class => [
            'class' => Redis::class,
            'calls' => [
                ['connect', [env('REDIS_HOST'), env('REDIS_PORT')]],
            ],
        ],
        RedisSessionHandler::class => [
            'arguments' => [service(Redis::class)],
        ],
    ],
]);

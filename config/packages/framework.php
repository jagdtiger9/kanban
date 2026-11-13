<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\App;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\RedisSessionHandler;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;

return App::config([
    'framework' => [
        'secret' => env('APP_SECRET'),
        'http_method_override' => false,
        'session' => [
            'enabled' => true,
            'handler_id' => RedisSessionHandler::class,
            'cookie_secure' => 'auto',
            'cookie_samesite' => 'lax',
        ],
        'php_errors' => [
            'log' => true,
        ],
        'validation' => [
            'enabled' => true,
            'email_validation_mode' => 'html5',
        ],
    ],
    'when@test' => [
        'framework' => [
            'test' => true,
        ],
    ],
]);

<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\App;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;

return App::config([
    'framework' => [
        'secret' => env('APP_SECRET'),
        'http_method_override' => false,
        'session' => [
            'enabled' => true,
            'cookie_secure' => 'auto',
            'cookie_samesite' => 'lax',
        ],
        'php_errors' => [
            'log' => true,
        ],
    ],
]);

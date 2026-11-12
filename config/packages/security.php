<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\App;

return App::config([
    'security' => [
        // Step 3 wires the real firewalls and login; the provider/hasher target
        // the User entity that lands in step 2.
        'password_hashers' => [
            'App\User\Domain\Entity\User' => 'auto',
        ],
        'providers' => [
            'app_user_provider' => [
                'entity' => [
                    'class' => 'App\User\Domain\Entity\User',
                    'property' => 'email',
                ],
            ],
        ],
        'firewalls' => [
            'main' => [
                'lazy' => true,
            ],
        ],
    ],
]);

<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\App;

return App::config([
    'framework' => [
        // Enables test.service_container for KernelTestCase-based tests.
        'test' => true,
    ],
]);

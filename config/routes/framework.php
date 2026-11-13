<?php

# Pre-migration config/routes/framework.yaml, kept for reference:
#
# when@dev:
#     _errors:
#         resource: '@FrameworkBundle/Resources/config/routing/errors.php'
#         prefix: /_error

namespace Symfony\Component\Routing\Loader\Configurator;

$config = Routes::config([
    'when@dev' => [
        '_errors' => [
            'resource' => '@FrameworkBundle/Resources/config/routing/errors.php',
            'prefix' => '/_error',
        ],
    ],
]);

return $config;

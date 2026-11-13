<?php

# Pre-migration config/routes/security.yaml, kept for reference:
#
# _security_logout:
#     resource: security.route_loader.logout
#     type: service

namespace Symfony\Component\Routing\Loader\Configurator;

$config = Routes::config([
    '_security_logout' => [
        'resource' => 'security.route_loader.logout',
        'type' => 'service',
    ],
]);

return $config;

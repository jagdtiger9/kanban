<?php

namespace Symfony\Component\Routing\Loader\Configurator;

// HTTP routes of the configured MCP servers (symfony/mcp-bundle).
return Routes::config([
    'mcp' => [
        'resource' => '.',
        'type' => 'mcp',
    ],
]);

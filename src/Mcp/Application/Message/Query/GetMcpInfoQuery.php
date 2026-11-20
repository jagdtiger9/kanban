<?php

declare(strict_types=1);

namespace App\Mcp\Application\Message\Query;

use Aljerom\SymfonyBoilerplate\CQRS\Query;

/**
 * Public facts about the MCP server for the settings page (its build number).
 */
final class GetMcpInfoQuery implements Query
{
}

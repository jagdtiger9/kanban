<?php

declare(strict_types=1);

namespace App\Mcp\Application\Message\Command;

use Aljerom\SymfonyBoilerplate\CQRS\Command;

final class IssueMcpTokenCommand implements Command
{
    public function __construct(
        public string $actorId = '',
    ) {
    }
}

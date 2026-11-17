<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Security;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class TooManyMcpRequestsException extends AuthenticationException
{
    public function __construct(
        private readonly int $retryAfterSeconds,
    ) {
        parent::__construct('Too many MCP requests');
    }

    public function retryAfterSeconds(): int
    {
        return $this->retryAfterSeconds;
    }

    public function getMessageKey(): string
    {
        return 'Too many requests, retry later.';
    }
}

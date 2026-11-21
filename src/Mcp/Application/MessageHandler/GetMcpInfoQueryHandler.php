<?php

declare(strict_types=1);

namespace App\Mcp\Application\MessageHandler;

use App\Mcp\Application\Message\Query\GetMcpInfoQuery;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetMcpInfoQueryHandler
{
    public function __construct(
        // plain build number 2, 3, 4… (config/packages/mcp.php)
        #[Autowire('%mcp.version%')]
        private string $version,
    ) {
    }

    /**
     * @return array{version: string}
     */
    public function __invoke(GetMcpInfoQuery $query): array
    {
        return ['version' => $this->version];
    }
}

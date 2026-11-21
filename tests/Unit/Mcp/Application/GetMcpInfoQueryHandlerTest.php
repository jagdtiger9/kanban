<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Application;

use App\Mcp\Application\Message\Query\GetMcpInfoQuery;
use App\Mcp\Application\MessageHandler\GetMcpInfoQueryHandler;
use PHPUnit\Framework\TestCase;

final class GetMcpInfoQueryHandlerTest extends TestCase
{
    public function testReturnsTheConfiguredBuildNumber(): void
    {
        $info = (new GetMcpInfoQueryHandler('7'))(new GetMcpInfoQuery());

        self::assertSame(['version' => '7'], $info);
    }
}

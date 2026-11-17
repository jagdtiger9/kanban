<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Application;

use App\Mcp\Application\Message\Command\IssueMcpTokenCommand;
use App\Mcp\Application\MessageHandler\IssueMcpTokenCommandHandler;
use App\Mcp\Application\Service\McpTokenVerifier;
use App\Mcp\Domain\ValueObject\McpTokenFormat;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Clock\MockClock;

final class IssueMcpTokenCommandHandlerTest extends TestCase
{
    public function testIssuesTokenValidFor90DaysAndStoresOnlyTheHash(): void
    {
        $repo = new InMemoryMcpTokenRepository();
        $clock = new MockClock('2026-11-17 10:00:00+00:00');
        $userId = Uuid::uuid4();

        $issued = (new IssueMcpTokenCommandHandler($repo, $clock))(new IssueMcpTokenCommand((string)$userId));

        $parsed = McpTokenFormat::parse($issued->token);
        self::assertNotNull($parsed);
        self::assertTrue($userId->equals($parsed[0]));
        self::assertGreaterThanOrEqual(43, strlen($parsed[1]), '32 random bytes, base64url');
        self::assertSame('2027-02-15T10:00:00+00:00', $issued->expiresAt->format(\DateTimeInterface::ATOM));

        $stored = $repo->findByUser($userId);
        self::assertNotNull($stored);
        self::assertSame(hash('sha256', $parsed[1]), $stored->secretHash);
        self::assertStringNotContainsString($parsed[1], $stored->secretHash);
    }

    public function testNewTokenReplacesThePreviousOne(): void
    {
        $repo = new InMemoryMcpTokenRepository();
        $clock = new MockClock('2026-11-17 10:00:00+00:00');
        $handler = new IssueMcpTokenCommandHandler($repo, $clock);
        $verifier = new McpTokenVerifier($repo, $clock);
        $userId = (string)Uuid::uuid4();

        $first = $handler(new IssueMcpTokenCommand($userId));
        $second = $handler(new IssueMcpTokenCommand($userId));

        self::assertNotSame($first->token, $second->token);
        self::assertNull($verifier->verify($first->token));
        self::assertNotNull($verifier->verify($second->token));
        self::assertCount(1, $repo->tokens);
    }
}

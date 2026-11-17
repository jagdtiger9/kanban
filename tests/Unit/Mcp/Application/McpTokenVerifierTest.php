<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Application;

use App\Mcp\Application\Service\McpTokenVerifier;
use App\Mcp\Domain\ValueObject\McpToken;
use App\Mcp\Domain\ValueObject\McpTokenFormat;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Clock\MockClock;

final class McpTokenVerifierTest extends TestCase
{
    public function testReturnsOwnerForValidToken(): void
    {
        $repo = new InMemoryMcpTokenRepository();
        $userId = Uuid::uuid4();
        $repo->save(new McpToken($userId, McpToken::hashSecret('secret'), new \DateTimeImmutable('2026-12-01')));

        $owner = (new McpTokenVerifier($repo, new MockClock('2026-11-17')))
            ->verify(McpTokenFormat::compose($userId, 'secret'));

        self::assertNotNull($owner);
        self::assertTrue($userId->equals($owner));
    }

    public function testRejectsWrongSecret(): void
    {
        $repo = new InMemoryMcpTokenRepository();
        $userId = Uuid::uuid4();
        $repo->save(new McpToken($userId, McpToken::hashSecret('secret'), new \DateTimeImmutable('2026-12-01')));

        self::assertNull((new McpTokenVerifier($repo, new MockClock('2026-11-17')))
            ->verify(McpTokenFormat::compose($userId, 'guess')));
    }

    public function testRejectsExpiredToken(): void
    {
        $repo = new InMemoryMcpTokenRepository();
        $userId = Uuid::uuid4();
        $repo->save(new McpToken($userId, McpToken::hashSecret('secret'), new \DateTimeImmutable('2026-12-01')));

        self::assertNull((new McpTokenVerifier($repo, new MockClock('2026-12-02')))
            ->verify(McpTokenFormat::compose($userId, 'secret')));
    }

    public function testRejectsUnknownUserAndMalformedToken(): void
    {
        $verifier = new McpTokenVerifier(new InMemoryMcpTokenRepository(), new MockClock('2026-11-17'));

        self::assertNull($verifier->verify(McpTokenFormat::compose(Uuid::uuid4(), 'secret')));
        self::assertNull($verifier->verify(''));
        self::assertNull($verifier->verify('garbage'));
    }
}

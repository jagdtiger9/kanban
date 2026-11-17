<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Domain;

use App\Mcp\Domain\ValueObject\McpToken;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class McpTokenTest extends TestCase
{
    public function testHashIsSha256OfSecret(): void
    {
        self::assertSame(hash('sha256', 'secret'), McpToken::hashSecret('secret'));
    }

    public function testMatchesTheRightSecretBeforeExpiry(): void
    {
        $token = new McpToken(Uuid::uuid4(), McpToken::hashSecret('secret'), new \DateTimeImmutable('2026-12-01'));

        self::assertTrue($token->matches('secret', new \DateTimeImmutable('2026-11-30')));
    }

    public function testRejectsWrongSecret(): void
    {
        $token = new McpToken(Uuid::uuid4(), McpToken::hashSecret('secret'), new \DateTimeImmutable('2026-12-01'));

        self::assertFalse($token->matches('other', new \DateTimeImmutable('2026-11-30')));
    }

    public function testRejectsAtAndAfterExpiry(): void
    {
        $token = new McpToken(Uuid::uuid4(), McpToken::hashSecret('secret'), new \DateTimeImmutable('2026-12-01'));

        self::assertFalse($token->matches('secret', new \DateTimeImmutable('2026-12-01')));
        self::assertFalse($token->matches('secret', new \DateTimeImmutable('2026-12-02')));
    }
}

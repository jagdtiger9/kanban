<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Domain;

use App\Mcp\Domain\ValueObject\McpTokenFormat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class McpTokenFormatTest extends TestCase
{
    public function testComposeAndParseRoundTrip(): void
    {
        $userId = Uuid::uuid4();
        // base64url secrets may contain "_" and "-" themselves
        $secret = 'ab_c-d_EF';

        $token = McpTokenFormat::compose($userId, $secret);

        self::assertSame('knb_' . $userId->toString() . '_' . $secret, $token);
        $parsed = McpTokenFormat::parse($token);
        self::assertNotNull($parsed);
        self::assertTrue($userId->equals($parsed[0]));
        self::assertSame($secret, $parsed[1]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformed(): iterable
    {
        $id = '0b6f4a52-6a3a-4f61-9f0e-3a9c4b1d2e7f';

        yield 'empty' => [''];
        yield 'no prefix' => [$id . '_secret'];
        yield 'wrong prefix' => ['abc_' . $id . '_secret'];
        yield 'no secret' => ['knb_' . $id . '_'];
        yield 'no separator' => ['knb_' . $id . 'secret'];
        yield 'bad uuid' => ['knb_not-a-uuid-at-all-not-a-uuid-at-a_secret'];
        yield 'path traversal' => ['knb_../../../../../../../../etc/passwd_secret'];
    }

    #[DataProvider('malformed')]
    public function testRejectsMalformedTokens(string $token): void
    {
        self::assertNull(McpTokenFormat::parse($token));
    }
}

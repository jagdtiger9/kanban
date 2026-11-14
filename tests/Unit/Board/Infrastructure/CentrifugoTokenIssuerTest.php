<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Infrastructure;

use App\Board\Infrastructure\Realtime\CentrifugoTokenIssuer;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class CentrifugoTokenIssuerTest extends TestCase
{
    private const SECRET = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';
    private const URL = 'wss://kanban.local/connection/websocket';

    public function testIssuesConnectionAndSubscriptionTokens(): void
    {
        $userId = Uuid::uuid4();
        $token = $this->issuer()->issue($userId, 'project:abc');

        $key = new Key(self::SECRET, 'HS256');
        $connection = (array) JWT::decode($token->token, $key);
        $subscription = (array) JWT::decode($token->subscriptionToken, $key);

        self::assertSame(self::URL, $token->url);
        self::assertSame('project:abc', $token->channel);
        self::assertSame((string) $userId, $connection['sub']);
        self::assertArrayNotHasKey('channel', $connection);
        self::assertSame((string) $userId, $subscription['sub']);
        self::assertSame('project:abc', $subscription['channel']);
        self::assertSame($token->expiresAt, $connection['exp']);
        self::assertSame($token->expiresAt, $subscription['exp']);
    }

    public function testTokensExpireInTenMinutes(): void
    {
        $token = $this->issuer()->issue(Uuid::uuid4(), 'project:abc');

        self::assertGreaterThanOrEqual(time() + 599, $token->expiresAt);
        self::assertLessThanOrEqual(time() + 601, $token->expiresAt);
    }

    private function issuer(): CentrifugoTokenIssuer
    {
        return new CentrifugoTokenIssuer(self::URL, self::SECRET);
    }
}

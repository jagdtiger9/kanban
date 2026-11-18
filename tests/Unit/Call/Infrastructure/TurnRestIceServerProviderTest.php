<?php

declare(strict_types=1);

namespace App\Tests\Unit\Call\Infrastructure;

use App\Call\Infrastructure\Turn\TurnRestIceServerProvider;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Clock\MockClock;

final class TurnRestIceServerProviderTest extends TestCase
{
    public function testTurnGetsTemporaryCredentials(): void
    {
        $clock = new MockClock('@1800000000');
        $userId = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $provider = new TurnRestIceServerProvider($clock, 'stun:t.test:3478, turn:t.test:3478?transport=udp', 'secret', 3600);

        $servers = $provider->forUser($userId);

        self::assertSame(['urls' => ['stun:t.test:3478']], $servers[0]);
        self::assertSame(['turn:t.test:3478?transport=udp'], $servers[1]['urls']);
        $username = '1800003600:' . $userId;
        self::assertSame($username, $servers[1]['username']);
        self::assertSame(base64_encode(hash_hmac('sha1', $username, 'secret', true)), $servers[1]['credential']);
    }

    public function testEmptyConfigMeansNoIceServers(): void
    {
        $provider = new TurnRestIceServerProvider(new MockClock(), '', '', 3600);

        self::assertSame([], $provider->forUser(Uuid::uuid4()));
    }

    public function testTurnWithoutSecretIsSkipped(): void
    {
        $provider = new TurnRestIceServerProvider(new MockClock(), 'turn:t.test:3478', '', 3600);

        self::assertSame([], $provider->forUser(Uuid::uuid4()));
    }
}

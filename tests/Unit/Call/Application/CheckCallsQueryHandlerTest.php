<?php

declare(strict_types=1);

namespace App\Tests\Unit\Call\Application;

use App\Call\Application\Message\Query\CheckCallsQuery;
use App\Call\Application\MessageHandler\CheckCallsQueryHandler;
use App\Call\Application\Service\CallCheck;
use App\Call\Application\Service\CallCheckStatus;
use App\Call\Application\Service\CallDiagnostics;
use App\Call\Application\Service\CallToken;
use App\Call\Application\Service\CallTokenIssuer;
use App\Call\Application\Service\IceServerProvider;
use PHPUnit\Framework\TestCase;

final class CheckCallsQueryHandlerTest extends TestCase
{
    private const string ACTOR = '6f1c2a4e-0b9d-4c55-9a3e-2d7c8b1e4f60';

    private function handler(CallTokenIssuer $issuer): CheckCallsQueryHandler
    {
        $diagnostics = $this->createStub(CallDiagnostics::class);
        $diagnostics->method('run')->willReturn([
            CallCheck::fail('call_namespace', 'Namespace "call"', 'Centrifugo answers 102 unknown channel', 'Restart Centrifugo.'),
        ]);
        $ice = $this->createStub(IceServerProvider::class);
        $ice->method('forUser')->willReturn([['urls' => ['stun:turn.example:3478']]]);

        return new CheckCallsQueryHandler($diagnostics, $issuer, $ice);
    }

    private function issuer(string $url): CallTokenIssuer
    {
        $issuer = $this->createStub(CallTokenIssuer::class);
        $issuer->method('issue')->willReturn(new CallToken($url, 'call:' . self::ACTOR, 'conn', 'sub', 0));

        return $issuer;
    }

    public function testReportsServerChecksTokensAndIceServers(): void
    {
        $report = ($this->handler($this->issuer('wss://site/_ws_connection/websocket')))(new CheckCallsQuery(self::ACTOR));

        self::assertSame(['call_namespace', 'tokens'], array_map(static fn(CallCheck $c): string => $c->key, $report->checks));
        self::assertSame(CallCheckStatus::Fail, $report->checks[0]->status);
        self::assertSame(CallCheckStatus::Ok, $report->checks[1]->status);
        self::assertSame([['urls' => ['stun:turn.example:3478']]], $report->iceServers);
    }

    public function testTokenFailureIsReportedNotThrown(): void
    {
        $issuer = $this->createStub(CallTokenIssuer::class);
        $issuer->method('issue')->willThrowException(new \RuntimeException('secret is empty'));

        $report = ($this->handler($issuer))(new CheckCallsQuery(self::ACTOR));

        self::assertSame(CallCheckStatus::Fail, $report->checks[1]->status);
        self::assertStringContainsString('secret is empty', $report->checks[1]->detail);
    }

    public function testEmptyWebSocketUrlFails(): void
    {
        $report = ($this->handler($this->issuer('')))(new CheckCallsQuery(self::ACTOR));

        self::assertSame(CallCheckStatus::Fail, $report->checks[1]->status);
        self::assertStringContainsString('CENTRIFUGO_WS_URL', $report->checks[1]->detail);
    }

    public function testCrashingDiagnosticsStillGiveAReport(): void
    {
        $diagnostics = $this->createStub(CallDiagnostics::class);
        $diagnostics->method('run')->willThrowException(new \RuntimeException('boom'));
        $ice = $this->createStub(IceServerProvider::class);
        $ice->method('forUser')->willThrowException(new \RuntimeException('bad urls'));
        $handler = new CheckCallsQueryHandler($diagnostics, $this->issuer('wss://site/ws'), $ice);

        $report = $handler(new CheckCallsQuery(self::ACTOR));

        self::assertSame(['server', 'ice_servers', 'tokens'], array_map(static fn(CallCheck $c): string => $c->key, $report->checks));
        self::assertSame(CallCheckStatus::Fail, $report->checks[0]->status);
        self::assertStringContainsString('boom', $report->checks[0]->detail);
        self::assertSame([], $report->iceServers);
    }

    public function testCheckSerializesStatusAsString(): void
    {
        self::assertSame(
            ['key' => 'x', 'label' => 'X', 'status' => 'warn', 'detail' => 'detail', 'hint' => 'hint'],
            CallCheck::warn('x', 'X', 'detail', 'hint')->toArray(),
        );
    }
}

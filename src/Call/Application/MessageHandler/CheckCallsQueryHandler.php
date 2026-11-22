<?php

declare(strict_types=1);

namespace App\Call\Application\MessageHandler;

use App\Call\Application\Message\Query\CheckCallsQuery;
use App\Call\Application\Service\CallCheck;
use App\Call\Application\Service\CallDiagnostics;
use App\Call\Application\Service\CallsCheckReport;
use App\Call\Application\Service\CallTokenIssuer;
use App\Call\Application\Service\IceServerProvider;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CheckCallsQueryHandler
{
    public function __construct(
        private CallDiagnostics $diagnostics,
        private CallTokenIssuer $tokenIssuer,
        private IceServerProvider $iceServers,
    ) {
    }

    public function __invoke(CheckCallsQuery $query): CallsCheckReport
    {
        $actorId = Uuid::fromString($query->actorId);

        // The check page must always get an answer: failures become FAIL lines.
        try {
            $checks = $this->diagnostics->run();
        } catch (\Throwable $e) {
            $checks = [CallCheck::fail('server', 'Server checks', 'Could not run: ' . $e->getMessage(), 'Send this line to the developers.')];
        }
        try {
            $iceServers = $this->iceServers->forUser($actorId);
        } catch (\Throwable $e) {
            $iceServers = [];
            $checks[] = CallCheck::fail('ice_servers', 'ICE servers for the browser test', 'Could not build: ' . $e->getMessage(), 'Check CALL_ICE_URLS and CALL_TURN_SECRET.');
        }

        return new CallsCheckReport([...$checks, $this->tokenCheck($actorId)], $iceServers);
    }

    /**
     * The tokens the browser needs to connect to Centrifugo and subscribe to its call channel.
     */
    private function tokenCheck(UuidInterface $actorId): CallCheck
    {
        $label = 'Call tokens';
        try {
            $token = $this->tokenIssuer->issue($actorId);
        } catch (\Throwable $e) {
            return CallCheck::fail('tokens', $label, 'Could not issue tokens: ' . $e->getMessage(), 'Check CENTRIFUGO_TOKEN_SECRET and CENTRIFUGO_WS_URL in the app env.');
        }
        if ($token->url === '') {
            return CallCheck::fail('tokens', $label, 'CENTRIFUGO_WS_URL is empty', 'Set CENTRIFUGO_WS_URL to wss://<site>/_ws_connection/websocket.');
        }

        return CallCheck::ok('tokens', $label, sprintf(
            'Issued for %s with CENTRIFUGO_TOKEN_SECRET; CENTRIFUGO_WS_URL = %s; valid until %s',
            $token->channel,
            $token->url,
            date('Y-m-d H:i T', $token->expiresAt),
        ));
    }
}

<?php

declare(strict_types=1);

namespace App\Call\Infrastructure\Diagnostics;

use App\Call\Application\Service\CallCheck;
use App\Call\Application\Service\CallDiagnostics;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Checks from the server's side (docs/calls-server.md):
 * - Centrifugo server API answers (node, clients, channels), how long the node has been
 *   running (it reads its config only at start), which of the app's namespaces it
 *   accepts (a real probe publish per namespace, raw answers shown), and how many call
 *   channels are active right now;
 * - CALL_ICE_URLS / CALL_TURN_SECRET look sane, the STUN/TURN TCP ports accept
 *   connections, the turns: certificate is not about to expire.
 * UDP is not checked here: the browser test on the check page covers it.
 */
#[AsAlias(CallDiagnostics::class)]
final class ServerCallDiagnostics implements CallDiagnostics
{
    private const int TIMEOUT = 3;
    private const int UNKNOWN_CHANNEL = 102;
    /** Namespaces the app publishes to (board, user events, calls). */
    private const array NAMESPACES = ['kanban', 'user', 'call'];
    private const int CERT_WARN_DAYS = 14;

    /** Raw body of the last Centrifugo API answer, shown as is in the namespaces check. */
    private string $lastRawAnswer = '';

    public function __construct(
        private HttpClientInterface $httpClient,
        private ClockInterface $clock,
        #[Autowire('%centrifugo_url%')]
        private string $centrifugoUrl,
        #[Autowire('%centrifugo_api_key%')]
        private string $centrifugoApiKey,
        #[Autowire('%call.ice_urls%')]
        private string $iceUrls,
        #[Autowire('%call.turn_secret%')]
        private string $turnSecret,
        #[Autowire('%centrifugo_ws_url%')]
        private string $centrifugoWsUrl,
        #[Autowire('%centrifugo_token_secret%')]
        private string $centrifugoTokenSecret,
        #[Autowire('%call.max_participants%')]
        private int $maxParticipants,
        #[Autowire('%call.participant_ttl%')]
        private int $participantTtl,
        #[Autowire('%call.turn_credential_ttl%')]
        private int $turnCredentialTtl,
    ) {
    }

    public function run(): array
    {
        // Every step is isolated: a crash turns into a FAIL line, the others still run.
        $checks = [
            ...$this->guarded('settings', 'Current settings', fn(): array => [$this->settingsCheck()]),
            ...$this->guarded('centrifugo', 'Centrifugo checks', fn(): array => $this->centrifugoChecks()),
        ];
        $iceUrls = [];
        try {
            $iceUrls = $this->parseIceUrls();
        } catch (\Throwable $e) {
            $checks[] = $this->crashed('ice_settings', 'STUN/TURN settings', $e);
        }
        $checks = [...$checks, ...$this->guarded('ice_settings', 'STUN/TURN settings', fn(): array => [$this->iceSettingsCheck($iceUrls)])];
        foreach ($this->tcpTargets($iceUrls) as [$host, $port]) {
            $checks = [...$checks, ...$this->guarded('port', sprintf('STUN/TURN port %s:%d', $host, $port), fn(): array => [$this->tcpCheck($host, $port)])];
        }
        foreach ($this->tlsTargets($iceUrls) as [$host, $port]) {
            $checks = [...$checks, ...$this->guarded('cert', sprintf('TURN TLS certificate %s:%d', $host, $port), fn(): array => [$this->certificateCheck($host, $port)])];
        }

        return $checks;
    }

    /**
     * The settings calls run with right now. Secrets as ******** when present (fixed
     * length, nothing about the value leaks) or NOT SET.
     */
    private function settingsCheck(): CallCheck
    {
        $secret = static fn(string $value): string => $value === '' ? 'NOT SET' : '********';
        $value = static fn(string $value): string => $value === '' ? 'EMPTY' : $value;
        $lines = [
            'CENTRIFUGO_URL = ' . $value($this->centrifugoUrl),
            'CENTRIFUGO_WS_URL = ' . $value($this->centrifugoWsUrl),
            'CENTRIFUGO_API_KEY = ' . $secret($this->centrifugoApiKey),
            'CENTRIFUGO_TOKEN_SECRET = ' . $secret($this->centrifugoTokenSecret),
            'CALL_ICE_URLS = ' . $value($this->iceUrls),
            'CALL_TURN_SECRET = ' . $secret($this->turnSecret),
            sprintf('max participants %d, participant timeout %d s, TURN password lifetime %d s', $this->maxParticipants, $this->participantTtl, $this->turnCredentialTtl),
        ];
        $missing = array_filter($lines, static fn(string $line): bool => str_contains($line, 'NOT SET') || str_contains($line, 'EMPTY'));
        if ($missing !== []) {
            return CallCheck::warn('settings', 'Current settings', implode("\n", $lines), 'Empty or unset values are listed above; see docs/calls-server.md, "App env".');
        }

        return CallCheck::ok('settings', 'Current settings', implode("\n", $lines));
    }

    /**
     * @param callable(): list<CallCheck> $step
     *
     * @return list<CallCheck>
     */
    private function guarded(string $key, string $label, callable $step): array
    {
        try {
            return $step();
        } catch (\Throwable $e) {
            return [$this->crashed($key, $label, $e)];
        }
    }

    private function crashed(string $key, string $label, \Throwable $e): CallCheck
    {
        return CallCheck::fail($key, $label, sprintf('The check itself crashed: %s: %s', $e::class, $e->getMessage()), 'This is a bug or an unexpected answer; send this line to the developers.');
    }

    // ---- Centrifugo -----------------------------------------------------------

    /**
     * @return list<CallCheck>
     */
    private function centrifugoChecks(): array
    {
        $label = 'Centrifugo server API';
        if ($this->centrifugoUrl === '') {
            return [CallCheck::fail('centrifugo', $label, 'CENTRIFUGO_URL is empty', 'Set CENTRIFUGO_URL (server-to-server address of Centrifugo) in the app env.')];
        }

        try {
            $info = $this->api('info', []);
        } catch (\Throwable $e) {
            return [CallCheck::fail('centrifugo', $label, sprintf('%s (CENTRIFUGO_URL) is not reachable: %s', $this->centrifugoUrl, $e->getMessage()), 'Is Centrifugo running? Does CENTRIFUGO_URL point to it?')];
        }
        if ($info === null) {
            return [CallCheck::fail('centrifugo', $label, sprintf('%s answered, but the API key (CENTRIFUGO_API_KEY = %s) was rejected', $this->centrifugoUrl, $this->centrifugoApiKey === '' ? 'NOT SET' : '********'), 'CENTRIFUGO_API_KEY must equal CENTRIFUGO_HTTP_API_KEY of Centrifugo.')];
        }

        $nodes = $info['result']['nodes'] ?? [];
        $node = is_array($nodes) && isset($nodes[0]) && is_array($nodes[0]) ? $nodes[0] : [];
        $checks = [CallCheck::ok('centrifugo', $label, sprintf(
            'Reachable at %s (CENTRIFUGO_URL), API key accepted; Centrifugo %s, node "%s"; %d node(s); %s clients, %s users, %s channels',
            $this->centrifugoUrl,
            $this->str($node['version'] ?? null),
            $this->str($node['name'] ?? null),
            is_array($nodes) ? count($nodes) : 0,
            $this->str($node['num_clients'] ?? null),
            $this->str($node['num_users'] ?? null),
            $this->str($node['num_channels'] ?? null),
        ))];

        $uptime = $node['uptime'] ?? null;
        if (is_int($uptime)) {
            $started = $this->clock->now()->modify(sprintf('-%d seconds', $uptime));
            $checks[] = CallCheck::ok(
                'centrifugo_uptime',
                'Centrifugo running since',
                sprintf('%s (%s ago)', $started->format('Y-m-d H:i T'), $this->duration($uptime)),
                'Centrifugo reads its config only at start: if the config changed after this time, restart it.',
            );
        }

        $checks[] = $this->namespacesCheck();
        $checks[] = $this->activeCallChannelsCheck();

        return $checks;
    }

    /**
     * A real probe publish into each namespace the app uses, with Centrifugo's raw
     * answers: the running Centrifugo does not expose its config otherwise. "kanban"
     * and "user" working while "call" fails means the running config lacks "call".
     */
    private function namespacesCheck(): CallCheck
    {
        $label = 'Namespaces in the running Centrifugo';
        $names = self::NAMESPACES;

        $answers = [];
        $callAnswer = null;
        foreach ($names as $name) {
            try {
                $answer = $this->api('publish', ['channel' => $name . ':diagnostics', 'data' => ['type' => 'diagnostics']]);
                $code = $answer['error']['code'] ?? null;
                $raw = $answer === null ? 'API key rejected' : trim($this->lastRawAnswer);
            } catch (\Throwable $e) {
                $code = 'error';
                $raw = $e->getMessage();
            }
            $answers[] = sprintf('%s: %s', $name, $raw);
            if ($name === 'call') {
                $callAnswer = $code;
            }
        }
        $detail = implode('; ', $answers);

        if ($callAnswer === self::UNKNOWN_CHANNEL) {
            return CallCheck::fail('call_namespace', $label, $detail, 'The running Centrifugo does not know "call": add { "name": "call", "allow_subscribe_for_client": false } to channel.namespaces of the config it actually uses and restart it (it reads the config only at start).');
        }
        if ($callAnswer !== null) {
            return CallCheck::fail('call_namespace', $label, $detail, 'See docs/calls-server.md, "Console errors".');
        }

        return CallCheck::ok('call_namespace', $label, $detail);
    }

    private function activeCallChannelsCheck(): CallCheck
    {
        $label = 'Active call channels';
        try {
            $answer = $this->api('channels', ['pattern' => 'call:*']);
        } catch (\Throwable $e) {
            return CallCheck::warn('call_channels', $label, 'Could not list channels: ' . $e->getMessage(), 'Informational only.');
        }
        $channels = $answer['result']['channels'] ?? [];
        if (!is_array($channels)) {
            return CallCheck::warn('call_channels', $label, 'Unexpected answer', 'Informational only.');
        }
        $subscribers = 0;
        foreach ($channels as $channel) {
            $subscribers += is_array($channel) && is_int($channel['num_clients'] ?? null) ? $channel['num_clients'] : 0;
        }

        return CallCheck::ok('call_channels', $label, sprintf(
            '%d personal call channel(s) with %d subscriber connection(s) right now',
            count($channels),
            $subscribers,
        ), 'Every signed-in browser with a working connection holds one: 0 while people are online means nobody gets call invites.');
    }

    private function str(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '?';
    }

    /**
     * POST to the Centrifugo server API; null when the API key is rejected.
     *
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>|null
     */
    private function api(string $method, array $body): ?array
    {
        $response = $this->httpClient->request('POST', rtrim($this->centrifugoUrl, '/') . '/api/' . $method, [
            'headers' => ['X-API-Key' => $this->centrifugoApiKey],
            'json' => (object) $body,
            'timeout' => self::TIMEOUT,
        ]);
        $status = $response->getStatusCode();
        if ($status === 401 || $status === 403) {
            return null;
        }
        if ($status !== 200) {
            throw new \RuntimeException('HTTP ' . $status);
        }

        $this->lastRawAnswer = $response->getContent(false);

        return $response->toArray(false);
    }

    // ---- STUN / TURN ------------------------------------------------------------

    /**
     * @return list<array{scheme: string, host: string, port: int, transport: string}>
     */
    private function parseIceUrls(): array
    {
        $parsed = [];
        foreach (explode(',', $this->iceUrls) as $url) {
            $url = trim($url);
            if (!preg_match('~^(stun|turns?):([^?:]+)(?::(\d+))?(?:\?transport=(udp|tcp))?$~', $url, $m)) {
                continue;
            }
            $scheme = $m[1];
            $parsed[] = [
                'scheme' => $scheme,
                'host' => $m[2],
                'port' => isset($m[3]) && $m[3] !== '' ? (int) $m[3] : ($scheme === 'turns' ? 5349 : 3478),
                'transport' => $m[4] ?? ($scheme === 'turns' ? 'tcp' : 'udp'),
            ];
        }

        return $parsed;
    }

    /**
     * @param list<array{scheme: string, host: string, port: int, transport: string}> $urls
     */
    private function iceSettingsCheck(array $urls): CallCheck
    {
        $label = 'STUN/TURN settings';
        $count = static fn(string $scheme): int => count(array_filter($urls, static fn(array $u): bool => $u['scheme'] === $scheme));
        $turn = $count('turn') + $count('turns');
        $summary = sprintf('%d stun, %d turn, %d turns', $count('stun'), $count('turn'), $count('turns'));

        if ($urls === []) {
            return CallCheck::warn('ice_settings', $label, 'CALL_ICE_URLS is empty or unreadable', 'Without STUN/TURN only direct connections work (same network). Set CALL_ICE_URLS.');
        }
        if ($turn > 0 && $this->turnSecret === '') {
            return CallCheck::fail('ice_settings', $label, $summary . '; CALL_TURN_SECRET is empty', 'TURN entries are dropped without the secret. Set CALL_TURN_SECRET = coturn static-auth-secret.');
        }
        if ($turn === 0) {
            return CallCheck::warn('ice_settings', $label, $summary . '; CALL_ICE_URLS: ' . $this->iceUrls, 'No TURN: calls between different networks (mobile, office) may not connect.');
        }

        return CallCheck::ok('ice_settings', $label, sprintf('%s; CALL_TURN_SECRET = ********; CALL_ICE_URLS: %s', $summary, $this->iceUrls));
    }

    /**
     * Unique host:port of stun:/turn: URLs (TCP connect is all a server can test).
     *
     * @param list<array{scheme: string, host: string, port: int, transport: string}> $urls
     *
     * @return list<array{0: string, 1: int}>
     */
    private function tcpTargets(array $urls): array
    {
        $targets = [];
        foreach ($urls as $url) {
            if ($url['scheme'] !== 'turns') {
                $targets[$url['host'] . ':' . $url['port']] = [$url['host'], $url['port']];
            }
        }

        return array_values($targets);
    }

    /**
     * @param list<array{scheme: string, host: string, port: int, transport: string}> $urls
     *
     * @return list<array{0: string, 1: int}>
     */
    private function tlsTargets(array $urls): array
    {
        $targets = [];
        foreach ($urls as $url) {
            if ($url['scheme'] === 'turns') {
                $targets[$url['host'] . ':' . $url['port']] = [$url['host'], $url['port']];
            }
        }

        return array_values($targets);
    }

    private function tcpCheck(string $host, int $port): CallCheck
    {
        $label = sprintf('STUN/TURN port %s:%d (TCP)', $host, $port);
        $started = microtime(true);
        $socket = @stream_socket_client(sprintf('tcp://%s:%d', $host, $port), $errno, $error, self::TIMEOUT);
        $ms = (int) round((microtime(true) - $started) * 1000);
        if ($socket === false) {
            return CallCheck::fail('port_' . $host . '_' . $port, $label, sprintf('No connection: %s', $error !== '' ? $error : 'error ' . $errno), 'Is coturn running? Is the port open in the server firewall and the hosting panel?');
        }
        fclose($socket);

        return CallCheck::ok('port_' . $host . '_' . $port, $label, sprintf('Accepts connections in %d ms (%s; from the server itself, the browser test checks from outside)', $ms, gethostbyname($host)));
    }

    private function certificateCheck(string $host, int $port): CallCheck
    {
        $key = 'cert_' . $host . '_' . $port;
        $label = sprintf('TURN TLS certificate %s:%d', $host, $port);
        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
            'peer_name' => $host,
        ]]);
        $socket = @stream_socket_client(sprintf('ssl://%s:%d', $host, $port), $errno, $error, self::TIMEOUT, STREAM_CLIENT_CONNECT, $context);
        if ($socket === false) {
            return CallCheck::fail($key, $label, sprintf('No TLS connection: %s', $error !== '' ? $error : 'error ' . $errno), 'Is coturn listening on tls-listening-port with cert/pkey set? Is the port open?');
        }
        $params = stream_context_get_params($socket);
        fclose($socket);

        $certificate = $params['options']['ssl']['peer_certificate'] ?? null;
        $parsed = $certificate instanceof \OpenSSLCertificate ? openssl_x509_parse($certificate) : false;
        $validTo = is_array($parsed) && is_int($parsed['validTo_time_t'] ?? null) ? $parsed['validTo_time_t'] : null;
        if ($validTo === null) {
            return CallCheck::warn($key, $label, 'Connected, but the certificate could not be read', 'Check the coturn cert/pkey files.');
        }

        $days = (int) floor(($validTo - $this->clock->now()->getTimestamp()) / 86400);
        $until = date('Y-m-d', $validTo);
        $subject = is_array($parsed['subject'] ?? null) ? $this->str($parsed['subject']['CN'] ?? null) : '?';
        $issuer = is_array($parsed['issuer'] ?? null) ? $this->str($parsed['issuer']['O'] ?? ($parsed['issuer']['CN'] ?? null)) : '?';
        $names = is_string($parsed['extensions']['subjectAltName'] ?? null) ? $parsed['extensions']['subjectAltName'] : '';
        $owner = sprintf('CN %s, %s, issuer %s', $subject, $names !== '' ? $names : 'no SAN', $issuer);
        $nameMatches = str_contains($names, 'DNS:' . $host) || $subject === $host;
        if ($days < 0) {
            return CallCheck::fail($key, $label, sprintf('Expired on %s; %s', $until, $owner), 'Renew the certificate and copy it to /etc/coturn/certs, restart coturn.');
        }
        if ($days < self::CERT_WARN_DAYS) {
            return CallCheck::warn($key, $label, sprintf('Expires on %s (in %d days); %s', $until, $days, $owner), 'Renew the certificate soon and copy it to /etc/coturn/certs.');
        }

        if (!$nameMatches) {
            return CallCheck::warn($key, $label, sprintf('Valid until %s, but not issued for %s; %s', $until, $host, $owner), 'Browsers reject turns: with a certificate for another name. Use a certificate for this host.');
        }

        return CallCheck::ok($key, $label, sprintf('Valid until %s (%d days); %s', $until, $days, $owner));
    }

    private function duration(int $seconds): string
    {
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return $days > 0 ? sprintf('%d d %d h', $days, $hours) : sprintf('%d h %d min', $hours, $minutes);
    }
}

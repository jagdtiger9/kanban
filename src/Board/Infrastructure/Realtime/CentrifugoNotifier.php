<?php

declare(strict_types=1);

namespace App\Board\Infrastructure\Realtime;

use App\Board\Application\Service\RealtimeNotifierInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsAlias(RealtimeNotifierInterface::class)]
final class CentrifugoNotifier implements RealtimeNotifierInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        #[Autowire('%centrifugo_url%')]
        private string $url,
        #[Autowire('%centrifugo_api_key%')]
        private string $apiKey,
    ) {
    }

    public function notify(string $channel, string $event, ?string $actorId = null): void
    {
        try {
            $status = $this->httpClient->request('POST', $this->url . '/api/publish', [
                'headers' => ['X-API-Key' => $this->apiKey],
                'json' => [
                    'channel' => $channel,
                    'data' => [
                        'type' => 'board.changed',
                        'event' => $event,
                        'actorId' => $actorId,
                    ],
                ],
                'timeout' => 1,
            ])->getStatusCode();

            if ($status !== 200) {
                $this->logger->warning('Centrifugo rejected a publish', [
                    'channel' => $channel,
                    'event' => $event,
                    'status' => $status,
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Centrifugo publish failed', [
                'channel' => $channel,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

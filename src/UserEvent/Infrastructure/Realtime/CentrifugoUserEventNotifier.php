<?php

declare(strict_types=1);

namespace App\UserEvent\Infrastructure\Realtime;

use App\UserEvent\Application\Port\UserEventNotifierInterface;
use App\UserEvent\Application\Service\UserChannel;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsAlias(UserEventNotifierInterface::class)]
final class CentrifugoUserEventNotifier implements UserEventNotifierInterface
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

    public function notify(UuidInterface $userId, array $item): void
    {
        try {
            $status = $this->httpClient->request('POST', $this->url . '/api/publish', [
                'headers' => ['X-API-Key' => $this->apiKey],
                'json' => [
                    'channel' => UserChannel::forUser((string) $userId),
                    'data' => ['type' => 'user.event', 'data' => $item],
                ],
                'timeout' => 1,
            ])->getStatusCode();

            if ($status !== 200) {
                $this->logger->warning('Centrifugo rejected a user event publication', [
                    'userEventId' => $item['id'] ?? null,
                    'status' => $status,
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Centrifugo user event publication failed', [
                'userEventId' => $item['id'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

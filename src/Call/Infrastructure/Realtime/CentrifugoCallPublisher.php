<?php

declare(strict_types=1);

namespace App\Call\Infrastructure\Realtime;

use App\Call\Application\Service\CallChannel;
use App\Call\Application\Service\CallPublisher;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsAlias(CallPublisher::class)]
final class CentrifugoCallPublisher implements CallPublisher
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

    public function publish(array $userIds, array $data): void
    {
        if ($userIds === []) {
            return;
        }

        try {
            $status = $this->httpClient->request('POST', $this->url . '/api/broadcast', [
                'headers' => ['X-API-Key' => $this->apiKey],
                'json' => [
                    'channels' => array_map(CallChannel::forUser(...), $userIds),
                    'data' => $data,
                ],
                'timeout' => 1,
            ])->getStatusCode();

            if ($status !== 200) {
                $this->logger->warning('Centrifugo rejected a call broadcast', [
                    'type' => $data['type'] ?? null,
                    'status' => $status,
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Centrifugo call broadcast failed', [
                'type' => $data['type'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

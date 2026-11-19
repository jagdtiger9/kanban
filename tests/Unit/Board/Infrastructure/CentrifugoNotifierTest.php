<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Infrastructure;

use App\Board\Infrastructure\Realtime\CentrifugoNotifier;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class CentrifugoNotifierTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function notifier(): CentrifugoNotifier
    {
        return new CentrifugoNotifier($this->httpClient, $this->logger, 'http://centrifugo:8000', 'api-key');
    }

    public function testPublishesEventPayloadToChannel(): void
    {
        $actorId = Uuid::uuid4()->toString();
        $body = null;

        $this->httpClient->expects($this->once())->method('request')
            ->with(
                'POST',
                'http://centrifugo:8000/api/publish',
                $this->callback(static function (array $options) use ($actorId, &$body): bool {
                    $body = $options['json'];

                    return $options['headers']['X-API-Key'] === 'api-key'
                        && $options['timeout'] === 1
                        && $options['json']['channel'] === 'kanban:abc';
                }),
            )
            ->willReturn($this->response(200));
        $this->logger->expects($this->never())->method('warning');

        $this->notifier()->notify('kanban:abc', 'card.created', $actorId);

        self::assertSame([
            'type' => 'board.changed',
            'event' => 'card.created',
            'actorId' => $actorId,
        ], $body['data']);
    }

    public function testIncludesCardIdForCardScopedEvents(): void
    {
        $actorId = Uuid::uuid4()->toString();
        $cardId = Uuid::uuid4()->toString();
        $body = null;

        $this->httpClient->expects($this->once())->method('request')
            ->with('POST', 'http://centrifugo:8000/api/publish', $this->callback(
                static function (array $options) use (&$body): bool {
                    $body = $options['json'];

                    return true;
                },
            ))
            ->willReturn($this->response(200));

        $this->notifier()->notify('kanban:abc', 'comment.created', $actorId, $cardId);

        self::assertSame([
            'type' => 'board.changed',
            'event' => 'comment.created',
            'actorId' => $actorId,
            'cardId' => $cardId,
        ], $body['data']);
    }

    public function testPublishesNullActorWhenThereIsNoActor(): void
    {
        $body = null;

        $this->httpClient->expects($this->once())->method('request')
            ->with('POST', 'http://centrifugo:8000/api/publish', $this->callback(
                static function (array $options) use (&$body): bool {
                    $body = $options['json'];

                    return true;
                },
            ))
            ->willReturn($this->response(200));

        $this->notifier()->notify('kanban:abc', 'card.updated');

        self::assertNull($body['data']['actorId']);
    }

    public function testLogsAndSwallowsTransportFailure(): void
    {
        $this->httpClient->method('request')->willThrowException(new \RuntimeException('connection refused'));
        $this->logger->expects($this->once())->method('warning')->with(
            'Centrifugo publish failed',
            $this->callback(static fn(array $context): bool => $context['channel'] === 'kanban:abc'
                && $context['event'] === 'card.created'),
        );

        $this->notifier()->notify('kanban:abc', 'card.created', Uuid::uuid4()->toString());
    }

    public function testLogsWhenCentrifugoRejectsThePublish(): void
    {
        $this->httpClient->method('request')->willReturn($this->response(500));
        $this->logger->expects($this->once())->method('warning')->with(
            'Centrifugo rejected a publish',
            $this->callback(static fn(array $context): bool => $context['status'] === 500),
        );

        $this->notifier()->notify('kanban:abc', 'card.created', Uuid::uuid4()->toString());
    }

    private function response(int $status): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);

        return $response;
    }
}

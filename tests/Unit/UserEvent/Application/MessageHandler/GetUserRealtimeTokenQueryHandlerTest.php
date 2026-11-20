<?php

declare(strict_types=1);

namespace App\Tests\Unit\UserEvent\Application\MessageHandler;

use App\Board\Application\Service\RealtimeToken;
use App\Board\Application\Service\RealtimeTokenIssuerInterface;
use App\UserEvent\Application\Message\Query\GetUserRealtimeTokenQuery;
use App\UserEvent\Application\MessageHandler\GetUserRealtimeTokenQueryHandler;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class GetUserRealtimeTokenQueryHandlerTest extends TestCase
{
    public function testIssuesTokenForTheCallersOwnChannel(): void
    {
        $userId = Uuid::uuid4();
        $token = new RealtimeToken('ws://centrifugo', 'user:' . $userId, 'token', 'sub-token', 123);
        $issuer = $this->createMock(RealtimeTokenIssuerInterface::class);
        $issuer->expects($this->once())
            ->method('issue')
            ->with(
                $this->callback(static fn(UuidInterface $id): bool => $id->equals($userId)),
                'user:' . $userId,
            )
            ->willReturn($token);

        $result = new GetUserRealtimeTokenQueryHandler($issuer)(new GetUserRealtimeTokenQuery((string)$userId));

        self::assertSame($token, $result);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Infrastructure\Telegram;

use App\Notification\Domain\Notification;
use App\Notification\Infrastructure\Telegram\TelegramNotifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;

final class TelegramNotifierTest extends TestCase
{
    public function testSendsChatMessageThroughTelegramTransport(): void
    {
        $captured = null;
        $chatter = $this->createMock(ChatterInterface::class);
        $chatter->expects(self::once())
            ->method('send')
            ->willReturnCallback(function (MessageInterface $message) use (&$captured): void {
                $captured = $message;
            });

        $notifier = new TelegramNotifier($chatter);
        $notifier->notify(new Notification('New user registered', 'Alice <alice@example.com>'));

        self::assertInstanceOf(ChatMessage::class, $captured);
        self::assertSame('telegram', $captured->getTransport());
        self::assertSame("New user registered\n\nAlice <alice@example.com>", $captured->getSubject());
    }
}

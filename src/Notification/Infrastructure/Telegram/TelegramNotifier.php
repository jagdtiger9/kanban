<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Telegram;

use App\Notification\Domain\Notification;
use App\Notification\Domain\NotifierInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

#[AsAlias(NotifierInterface::class)]
final class TelegramNotifier implements NotifierInterface
{
    public function __construct(
        private ChatterInterface $chatter,
    ) {
    }

    public function notify(Notification $notification): void
    {
        $message = new ChatMessage($notification->title . "\n\n" . $notification->body)
            ->transport('telegram');

        $this->chatter->send($message);
    }
}

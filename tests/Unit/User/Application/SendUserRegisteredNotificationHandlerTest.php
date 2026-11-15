<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Application;

use App\Notification\Domain\Notification;
use App\Notification\Domain\NotifierInterface;
use App\User\Application\MessageHandler\SendUserRegisteredNotificationHandler;
use App\User\Domain\Event\UserRegistered;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class SendUserRegisteredNotificationHandlerTest extends TestCase
{
    public function testNotifiesAboutRegisteredUser(): void
    {
        $captured = null;
        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::once())
            ->method('notify')
            ->willReturnCallback(function (Notification $notification) use (&$captured): void {
                $captured = $notification;
            });

        $handler = new SendUserRegisteredNotificationHandler($notifier);
        $handler->onUserRegistered(new UserRegistered(Uuid::uuid4()->toString(), 'alice@example.com', 'Alice'));

        self::assertSame('New user registered', $captured->title);
        self::assertSame('Alice <alice@example.com>', $captured->body);
    }
}

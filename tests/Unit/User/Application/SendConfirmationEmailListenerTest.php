<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Application;

use App\User\Application\EventListener\SendConfirmationEmailListener;
use App\User\Application\Service\ConfirmationEmailSender;
use App\User\Domain\Event\UserRegistered;
use PHPUnit\Framework\TestCase;

final class SendConfirmationEmailListenerTest extends TestCase
{
    public function testSendsEmailUsingEventData(): void
    {
        $sender = $this->createMock(ConfirmationEmailSender::class);
        $sender->expects(self::once())
            ->method('send')
            ->with('alice@example.com', 'Alice', 'deadbeef');

        $listener = new SendConfirmationEmailListener($sender);
        $listener->onUserRegistered(new UserRegistered('alice@example.com', 'Alice', 'deadbeef'));
    }
}

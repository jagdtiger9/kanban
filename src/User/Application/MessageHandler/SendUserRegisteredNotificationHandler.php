<?php

declare(strict_types=1);

namespace App\User\Application\MessageHandler;

use App\Notification\Domain\Notification;
use App\Notification\Domain\NotifierInterface;
use App\User\Domain\Event\UserRegistered;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final class SendUserRegisteredNotificationHandler
{
    public function __construct(
        private NotifierInterface $notifier,
    ) {
    }

    #[AsMessageHandler]
    public function onUserRegistered(UserRegistered $event): void
    {
        $this->notifier->notify(new Notification(
            'New user registered',
            sprintf('%s <%s>', $event->displayName, $event->email),
        ));
    }
}

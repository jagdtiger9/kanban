<?php

declare(strict_types=1);

namespace App\User\Application\EventListener;

use App\User\Application\Service\ConfirmationEmailSender;
use App\User\Domain\Event\UserRegistered;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class SendConfirmationEmailListener
{
    public function __construct(private ConfirmationEmailSender $sender)
    {
    }

    #[AsEventListener]
    public function onUserRegistered(UserRegistered $event): void
    {
        $this->sender->send($event->email, $event->displayName, $event->confirmationToken);
    }
}

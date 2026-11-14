<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Mailer;

use App\User\Application\Service\ConfirmationEmailSender;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsAlias(ConfirmationEmailSender::class)]
final class MailerConfirmationEmailSender implements ConfirmationEmailSender
{
    public function __construct(
        private MailerInterface $mailer,
        #[Autowire('%frontend_url%')]
        private string $frontendUrl,
    ) {
    }

    public function send(string $email, string $displayName, string $confirmationToken): void
    {
        $confirmUrl = sprintf('%s/confirm/%s', rtrim($this->frontendUrl, '/'), $confirmationToken);

        $message = (new Email())
            ->to($email)
            ->subject('Confirm your Kanban account')
            ->text(sprintf(
                "Hello %s,\n\n"
                . "Thanks for registering at Kanban. Please confirm your email address by opening the link below:\n\n"
                . "%s\n\n"
                . "The link is valid for 24 hours.\n\n"
                . "If you didn't create this account, you can ignore this email.\n",
                $displayName,
                $confirmUrl,
            ));

        $this->mailer->send($message);
    }
}

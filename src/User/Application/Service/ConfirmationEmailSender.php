<?php

declare(strict_types=1);

namespace App\User\Application\Service;

interface ConfirmationEmailSender
{
    public function send(string $email, string $displayName, string $confirmationToken): void;
}

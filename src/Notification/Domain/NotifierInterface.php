<?php

declare(strict_types=1);

namespace App\Notification\Domain;

interface NotifierInterface
{
    public function notify(Notification $notification): void;
}

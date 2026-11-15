<?php

declare(strict_types=1);

namespace App\Notification\Domain;

final readonly class Notification
{
    public function __construct(
        public string $title,
        public string $body,
    ) {
    }
}

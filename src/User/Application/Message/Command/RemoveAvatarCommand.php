<?php

declare(strict_types=1);

namespace App\User\Application\Message\Command;

use Aljerom\SymfonyBoilerplate\CQRS\Command;

final class RemoveAvatarCommand implements Command
{
    public function __construct(public string $actorId = '')
    {
    }
}

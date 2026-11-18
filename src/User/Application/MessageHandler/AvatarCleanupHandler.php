<?php

declare(strict_types=1);

namespace App\User\Application\MessageHandler;

use App\Shared\Application\Storage\FileStorage;
use App\User\Domain\Event\UserAvatarChanged;
use App\User\Domain\Event\UserAvatarRemoved;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final class AvatarCleanupHandler
{
    public function __construct(private FileStorage $storage)
    {
    }

    #[AsMessageHandler]
    public function onAvatarChanged(UserAvatarChanged $event): void
    {
        if ($event->previousStorageKey !== null) {
            $this->storage->delete($event->previousStorageKey);
        }
    }

    #[AsMessageHandler]
    public function onAvatarRemoved(UserAvatarRemoved $event): void
    {
        $this->storage->delete($event->storageKey);
    }
}

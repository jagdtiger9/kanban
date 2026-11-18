<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Domain\Event\AttachmentRemoved;
use App\Shared\Application\Storage\FileStorage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AttachmentCleanupHandler
{
    public function __construct(private FileStorage $storage)
    {
    }

    public function __invoke(AttachmentRemoved $event): void
    {
        $this->storage->delete($event->storageKey);

        if ($event->thumbnailStorageKey !== null) {
            $this->storage->delete($event->thumbnailStorageKey);
        }
    }
}

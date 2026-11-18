<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Message\Query\GetAttachmentThumbnailQuery;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Exception\AttachmentNotFoundException;
use App\Board\Domain\Repository\AttachmentRepository;
use App\Shared\Application\Storage\FileStorage;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetAttachmentThumbnailQueryHandler
{
    public function __construct(
        private CardGuard $guard,
        private AttachmentRepository $attachmentRepository,
        private FileStorage $storage,
    ) {
    }

    /**
     * @return array{stream: resource}
     */
    public function __invoke(GetAttachmentThumbnailQuery $query): array
    {
        $attachment = $this->attachmentRepository->findById(Uuid::fromString($query->attachmentId));
        $thumbnailKey = $attachment?->thumbnailStorageKey();
        if ($attachment === null || $thumbnailKey === null) {
            throw new AttachmentNotFoundException('Attachment thumbnail not found');
        }
        $this->guard->requireCard($attachment->cardId(), Uuid::fromString($query->actorId));

        return ['stream' => $this->storage->open($thumbnailKey)];
    }
}

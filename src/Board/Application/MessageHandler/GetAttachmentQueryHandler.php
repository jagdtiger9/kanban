<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Message\Query\GetAttachmentQuery;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Exception\AttachmentNotFoundException;
use App\Board\Domain\Repository\AttachmentRepository;
use App\Shared\Application\Storage\FileStorage;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetAttachmentQueryHandler
{
    public function __construct(
        private CardGuard $guard,
        private AttachmentRepository $attachmentRepository,
        private FileStorage $storage,
    ) {
    }

    /**
     * @return array{attachment: \App\Board\Domain\Entity\Attachment, stream: resource}
     */
    public function __invoke(GetAttachmentQuery $query): array
    {
        $attachment = $this->attachmentRepository->findById(Uuid::fromString($query->attachmentId));
        if ($attachment === null) {
            throw new AttachmentNotFoundException('Attachment not found');
        }
        $this->guard->requireCard($attachment->cardId(), Uuid::fromString($query->actorId));

        return [
            'attachment' => $attachment,
            'stream' => $this->storage->open($attachment->storageKey()),
        ];
    }
}

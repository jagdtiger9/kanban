<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Message\Command\RemoveCardAttachmentCommand;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Exception\AttachmentNotFoundException;
use App\Board\Domain\Repository\AttachmentRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RemoveCardAttachmentCommandHandler
{
    public function __construct(
        private CardGuard $guard,
        private AttachmentRepository $attachmentRepository,
    ) {
    }

    public function __invoke(RemoveCardAttachmentCommand $command): void
    {
        $actorId = Uuid::fromString($command->actorId);
        $cardId = Uuid::fromString($command->cardId);

        $this->guard->requireCard($cardId, $actorId);

        $attachment = $this->attachmentRepository->findById(Uuid::fromString($command->attachmentId));
        if ($attachment === null || $attachment->isCommentAttachment() || !$attachment->cardId()->equals($cardId)) {
            throw new AttachmentNotFoundException('Attachment not found');
        }

        $attachment->remove($actorId);
        $this->attachmentRepository->remove($attachment);
    }
}

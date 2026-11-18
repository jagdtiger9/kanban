<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Message\Command\AddCardAttachmentCommand;
use App\Board\Application\Service\AttachmentUploader;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\Attachment;
use App\Board\Domain\Exception\AttachmentLimitException;
use App\Board\Domain\Repository\AttachmentRepository;
use App\Shared\Application\Media\MediaPolicy;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AddCardAttachmentCommandHandler
{
    public function __construct(
        private CardGuard $guard,
        private AttachmentRepository $attachmentRepository,
        private AttachmentUploader $uploader,
    ) {
    }

    public function __invoke(AddCardAttachmentCommand $command): Attachment
    {
        $actorId = Uuid::fromString($command->actorId);
        $cardId = Uuid::fromString($command->cardId);

        $card = $this->guard->requireCard($cardId, $actorId);
        $projectId = $this->guard->resolveBoard($card)->projectId();

        $file = $command->file;
        if ($file === null) {
            throw new \LogicException('Attachment file must be validated before reaching the handler');
        }

        $usage = $this->attachmentRepository->usageByCard($cardId);
        if ($usage['files'] + 1 > MediaPolicy::MAX_FILES_PER_CARD
            || $usage['bytes'] + (int)$file->getSize() > MediaPolicy::MAX_BYTES_PER_CARD) {
            throw new AttachmentLimitException('This card has reached its attachment limit');
        }

        $attachment = $this->uploader->store($projectId, $cardId, null, $actorId, $file);
        $this->attachmentRepository->save($attachment);

        return $attachment;
    }
}

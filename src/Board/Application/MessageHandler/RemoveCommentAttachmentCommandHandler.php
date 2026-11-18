<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Message\Command\RemoveCommentAttachmentCommand;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\Attachment;
use App\Board\Domain\Exception\AttachmentNotFoundException;
use App\Board\Domain\Exception\CommentNotFoundException;
use App\Board\Domain\Repository\AttachmentRepository;
use App\Board\Domain\Repository\CommentRepository;
use App\Board\Domain\Service\AttachmentPolicy;
use App\Project\Domain\Enum\MemberRole;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RemoveCommentAttachmentCommandHandler
{
    public function __construct(
        private CardGuard $guard,
        private CommentRepository $commentRepository,
        private AttachmentRepository $attachmentRepository,
        private AttachmentPolicy $policy,
    ) {
    }

    public function __invoke(RemoveCommentAttachmentCommand $command): void
    {
        $actorId = Uuid::fromString($command->actorId);
        $commentId = Uuid::fromString($command->commentId);

        $comment = $this->commentRepository->findById($commentId);
        if ($comment === null) {
            throw new CommentNotFoundException('Comment not found');
        }

        $card = $this->guard->requireCard($comment->cardId(), $actorId);

        $attachment = $this->findAttachment($commentId, Uuid::fromString($command->attachmentId), $comment->cardId());
        $this->policy->assertCanRemove(
            $attachment,
            $actorId,
            $comment->authorId(),
            $this->guard->effectiveRoleOnCard($card, $actorId) === MemberRole::Admin,
        );

        $attachment->remove($actorId);
        $this->attachmentRepository->remove($attachment);
    }

    private function findAttachment(
        UuidInterface $commentId,
        UuidInterface $attachmentId,
        UuidInterface $cardId,
    ): Attachment {
        $attachment = $this->attachmentRepository->findById($attachmentId);
        if ($attachment === null
            || !$attachment->cardId()->equals($cardId)
            || !$attachment->commentId()?->equals($commentId)) {
            throw new AttachmentNotFoundException('Attachment not found');
        }

        return $attachment;
    }
}

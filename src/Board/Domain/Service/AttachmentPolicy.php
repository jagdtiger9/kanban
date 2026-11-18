<?php

declare(strict_types=1);

namespace App\Board\Domain\Service;

use App\Board\Domain\Entity\Attachment;
use App\Board\Domain\Exception\AttachmentForbiddenException;
use Ramsey\Uuid\UuidInterface;

final class AttachmentPolicy
{
    public function assertCanRemove(
        Attachment $attachment,
        UuidInterface $actorId,
        ?UuidInterface $commentAuthorId,
        bool $actorIsProjectAdmin,
    ): void {
        if (!$attachment->isCommentAttachment() || $actorIsProjectAdmin) {
            return;
        }

        if ($commentAuthorId !== null && $commentAuthorId->equals($actorId)) {
            return;
        }

        throw new AttachmentForbiddenException('Only the comment author or a project admin can remove this attachment');
    }
}

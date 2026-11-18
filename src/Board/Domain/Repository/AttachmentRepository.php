<?php

declare(strict_types=1);

namespace App\Board\Domain\Repository;

use App\Board\Domain\Entity\Attachment;
use Ramsey\Uuid\UuidInterface;

interface AttachmentRepository
{
    public function save(Attachment $attachment): void;

    public function findById(UuidInterface $id): ?Attachment;

    /** @return list<Attachment> card-level attachments ordered by creation time */
    public function findCardAttachments(UuidInterface $cardId): array;

    /** @return list<Attachment> every attachment on the card, ordered by creation time */
    public function findByCard(UuidInterface $cardId): array;

    /** @return list<Attachment> ordered by creation time */
    public function findByComment(UuidInterface $commentId): array;

    /** @return array{files: int, bytes: int} */
    public function usageByCard(UuidInterface $cardId): array;

    /** @return list<string> every original and thumbnail key, for media sweeps */
    public function allStorageKeys(): array;

    public function remove(Attachment $attachment): void;
}

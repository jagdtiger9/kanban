<?php

declare(strict_types=1);

namespace App\Board\Domain\Repository;

use App\Board\Domain\Entity\Comment;
use Ramsey\Uuid\UuidInterface;

interface CommentRepository
{
    public function save(Comment $comment): void;

    public function findById(UuidInterface $id): ?Comment;

    /** @return list<Comment> ordered by creation time */
    public function findByCard(UuidInterface $cardId): array;

    public function remove(Comment $comment): void;
}

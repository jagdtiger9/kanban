<?php

declare(strict_types=1);

namespace App\Board\Domain\Event;

use Ramsey\Uuid\UuidInterface;

final readonly class CommentCreated
{
    public function __construct(
        public UuidInterface $cardId,
        public UuidInterface $commentId,
        public UuidInterface $authorId,
    ) {
    }
}

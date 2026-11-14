<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Message\Command\CreateCommentCommand;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\Comment;
use App\Board\Domain\Repository\CommentRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateCommentCommandHandler
{
    public function __construct(
        private CardGuard $guard,
        private CommentRepository $commentRepository,
    ) {
    }

    public function __invoke(CreateCommentCommand $command): Comment
    {
        $actorId = Uuid::fromString($command->actorId);
        $cardId = Uuid::fromString($command->cardId);

        $this->guard->requireCard($cardId, $actorId);

        $comment = Comment::create(Uuid::uuid4(), $cardId, $actorId, $command->body);
        $this->commentRepository->save($comment);

        return $comment;
    }
}

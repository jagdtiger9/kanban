<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Message\Command\UpdateCommentCommand;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\Comment;
use App\Board\Domain\Exception\CommentNotFoundException;
use App\Board\Domain\Repository\CommentRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class UpdateCommentCommandHandler
{
    public function __construct(
        private CardGuard $guard,
        private CommentRepository $commentRepository,
    ) {
    }

    public function __invoke(UpdateCommentCommand $command): Comment
    {
        $actorId = Uuid::fromString($command->actorId);

        $comment = $this->commentRepository->findById(Uuid::fromString($command->commentId));
        if ($comment === null) {
            throw new CommentNotFoundException('Comment not found');
        }
        $this->guard->requireCommentEditable($comment, $actorId);

        $comment->edit($command->body);
        $this->commentRepository->save($comment);

        return $comment;
    }
}

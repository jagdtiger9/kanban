<?php

declare(strict_types=1);

namespace App\Board\Application\Service;

use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Entity\Comment;
use App\Board\Domain\Enum\ColumnKind;
use App\Board\Domain\Exception\CardForbiddenException;
use App\Board\Domain\Exception\CardKindMismatchException;
use App\Board\Domain\Exception\CardNotFoundException;
use App\Board\Domain\Exception\CommentForbiddenException;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use App\Project\Application\Service\MembershipGuard;
use App\Project\Domain\Enum\MemberRole;
use Ramsey\Uuid\UuidInterface;

final class CardGuard
{
    public function __construct(
        private CardRepository $cardRepository,
        private ColumnRepository $columnRepository,
        private BoardRepository $boardRepository,
        private MembershipGuard $guard,
    ) {
    }

    public function requireCard(UuidInterface $cardId, UuidInterface $actorId): Card
    {
        $card = $this->cardRepository->findById($cardId);
        if ($card === null) {
            throw new CardNotFoundException('Card not found');
        }
        $this->guard->requireMember($this->resolveBoard($card)->projectId(), $actorId);

        return $card;
    }

    public function requireCardEditable(UuidInterface $cardId, UuidInterface $actorId): Card
    {
        $card = $this->requireCard($cardId, $actorId);
        if (!$card->creatorId()->equals($actorId)
            && $this->effectiveRoleOnCard($card, $actorId) !== MemberRole::Admin) {
            throw new CardForbiddenException('Only the card creator or a project admin can edit or archive this card');
        }

        return $card;
    }

    public function requireCompletable(Card $card, UuidInterface $actorId): void
    {
        if ($card->executorId() !== null && !$card->executorId()->equals($actorId)) {
            throw new CardForbiddenException('Only the card executor can mark this card completed');
        }
    }

    public function requireTaskCard(Card $card): void
    {
        if ($this->column($card)->kind() !== ColumnKind::Task) {
            throw new CardKindMismatchException('Sticker cards have no schedule');
        }
    }

    public function requireSameColumnKind(Card $card, Column $targetColumn): void
    {
        if ($this->column($card)->kind() !== $targetColumn->kind()) {
            throw new CardKindMismatchException('Cards cannot be moved between task and sticker columns');
        }
    }

    public function requireCommentDeletable(Comment $comment, UuidInterface $actorId): void
    {
        $card = $this->requireCard($comment->cardId(), $actorId);
        if ($comment->authorId()->equals($actorId)) {
            return;
        }
        if ($this->effectiveRoleOnCard($card, $actorId) !== MemberRole::Admin) {
            throw new CommentForbiddenException('Only the comment author or a project admin can delete this comment');
        }
    }

    public function requireCommentEditable(Comment $comment, UuidInterface $actorId): void
    {
        $this->requireCard($comment->cardId(), $actorId);
        if (!$comment->authorId()->equals($actorId)) {
            throw new CommentForbiddenException('Only the comment author can edit this comment');
        }
    }

    public function effectiveRoleOnCard(Card $card, UuidInterface $actorId): ?MemberRole
    {
        return $this->guard->effectiveRole($this->resolveBoard($card)->projectId(), $actorId);
    }

    public function column(Card $card): Column
    {
        $column = $this->columnRepository->findById($card->columnId());
        if ($column === null) {
            throw new \LogicException('Column must exist for every card');
        }

        return $column;
    }

    public function resolveBoard(Card $card): Board
    {
        $board = $this->boardRepository->findById($this->column($card)->boardId());
        if ($board === null) {
            throw new \LogicException('Board must exist for every column');
        }

        return $board;
    }
}

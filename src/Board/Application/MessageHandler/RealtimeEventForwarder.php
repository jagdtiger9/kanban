<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Service\BoardChannelResolver;
use App\Board\Application\Service\RealtimeNotifierInterface;
use App\Board\Domain\Event\CardArchived;
use App\Board\Domain\Event\CardAssigned;
use App\Board\Domain\Event\CardCreated;
use App\Board\Domain\Event\CardFieldChanged;
use App\Board\Domain\Event\CardLabelAdded;
use App\Board\Domain\Event\CardLabelRemoved;
use App\Board\Domain\Event\CardMoved;
use App\Board\Domain\Event\CardRestored;
use App\Board\Domain\Event\CardUnassigned;
use App\Board\Domain\Event\ColumnCreated;
use App\Board\Domain\Event\ColumnDeleted;
use App\Board\Domain\Event\ColumnRenamed;
use App\Board\Domain\Event\ColumnReordered;
use App\Board\Domain\Event\CommentCreated;
use App\Board\Domain\Event\CommentDeleted;
use App\Board\Domain\Event\CommentEdited;
use App\Board\Domain\Event\LabelCreated;
use App\Board\Domain\Event\LabelDeleted;
use App\Board\Domain\Event\LabelUpdated;
use App\Project\Domain\Event\ProjectArchived;
use App\Project\Domain\Event\ProjectMemberRoleChanged;
use App\Project\Domain\Event\ProjectRestored;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final class RealtimeEventForwarder
{
    public function __construct(
        private BoardChannelResolver $channels,
        private RealtimeNotifierInterface $notifier,
    ) {
    }

    #[AsMessageHandler]
    public function onCardCreated(CardCreated $event): void
    {
        $this->notifyForCard($event->cardId, 'card.created', $event->actorId);
    }

    #[AsMessageHandler]
    public function onCardFieldChanged(CardFieldChanged $event): void
    {
        $this->notifyForCard($event->cardId, 'card.updated', $event->actorId);
    }

    #[AsMessageHandler]
    public function onCardMoved(CardMoved $event): void
    {
        $this->notifyForCard($event->cardId, 'card.moved', $event->actorId);
    }

    #[AsMessageHandler]
    public function onCardArchived(CardArchived $event): void
    {
        $this->notifyForCard($event->cardId, 'card.archived', $event->actorId);
    }

    #[AsMessageHandler]
    public function onCardRestored(CardRestored $event): void
    {
        $this->notifyForCard($event->cardId, 'card.restored', $event->actorId);
    }

    #[AsMessageHandler]
    public function onCardAssigned(CardAssigned $event): void
    {
        $this->notifyForCard($event->cardId, 'card.assigned', $event->actorId);
    }

    #[AsMessageHandler]
    public function onCardUnassigned(CardUnassigned $event): void
    {
        $this->notifyForCard($event->cardId, 'card.unassigned', $event->actorId);
    }

    #[AsMessageHandler]
    public function onCardLabelAdded(CardLabelAdded $event): void
    {
        $this->notifyForCard($event->cardId, 'card.label_added', $event->actorId);
    }

    #[AsMessageHandler]
    public function onCardLabelRemoved(CardLabelRemoved $event): void
    {
        $this->notifyForCard($event->cardId, 'card.label_removed', $event->actorId);
    }

    #[AsMessageHandler]
    public function onCommentCreated(CommentCreated $event): void
    {
        $this->notifyForCard($event->cardId, 'comment.created', $event->authorId);
    }

    #[AsMessageHandler]
    public function onCommentEdited(CommentEdited $event): void
    {
        $this->notifyForCard($event->cardId, 'comment.updated', $event->authorId);
    }

    #[AsMessageHandler]
    public function onCommentDeleted(CommentDeleted $event): void
    {
        $this->notifyForCard($event->cardId, 'comment.deleted', $event->actorId);
    }

    #[AsMessageHandler]
    public function onColumnCreated(ColumnCreated $event): void
    {
        $this->notifyForBoard($event->boardId, 'column.created', $event->actorId);
    }

    #[AsMessageHandler]
    public function onColumnRenamed(ColumnRenamed $event): void
    {
        $this->notifyForBoard($event->boardId, 'column.renamed', $event->actorId);
    }

    #[AsMessageHandler]
    public function onColumnDeleted(ColumnDeleted $event): void
    {
        $this->notifyForBoard($event->boardId, 'column.deleted', $event->actorId);
    }

    #[AsMessageHandler]
    public function onColumnReordered(ColumnReordered $event): void
    {
        $this->notifyForBoard($event->boardId, 'column.reordered', $event->actorId);
    }

    #[AsMessageHandler]
    public function onLabelCreated(LabelCreated $event): void
    {
        $this->notifyForBoard($event->boardId, 'label.created', $event->actorId);
    }

    #[AsMessageHandler]
    public function onLabelUpdated(LabelUpdated $event): void
    {
        $this->notifyForBoard($event->boardId, 'label.updated', $event->actorId);
    }

    #[AsMessageHandler]
    public function onLabelDeleted(LabelDeleted $event): void
    {
        $this->notifyForBoard($event->boardId, 'label.deleted', $event->actorId);
    }

    #[AsMessageHandler]
    public function onProjectArchived(ProjectArchived $event): void
    {
        $this->notifyForProject($event->projectId, 'project.archived', $event->actorId);
    }

    #[AsMessageHandler]
    public function onProjectRestored(ProjectRestored $event): void
    {
        $this->notifyForProject($event->projectId, 'project.restored', $event->actorId);
    }

    #[AsMessageHandler]
    public function onProjectMemberRoleChanged(ProjectMemberRoleChanged $event): void
    {
        $this->notifyForProject($event->projectId, 'project.member_role_changed', $event->actorId);
    }

    private function notifyForCard(string $cardId, string $event, ?string $actorId): void
    {
        $channel = $this->channels->forCard($cardId);
        if ($channel !== null) {
            $this->notifier->notify($channel, $event, $actorId);
        }
    }

    private function notifyForBoard(string $boardId, string $event, ?string $actorId): void
    {
        $channel = $this->channels->forBoard($boardId);
        if ($channel !== null) {
            $this->notifier->notify($channel, $event, $actorId);
        }
    }

    private function notifyForProject(string $projectId, string $event, ?string $actorId): void
    {
        $this->notifier->notify($this->channels->forProject($projectId), $event, $actorId);
    }
}

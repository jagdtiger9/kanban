<?php

declare(strict_types=1);

namespace App\Board\Application\EventListener;

use App\Board\Domain\Entity\CardActivity;
use App\Board\Domain\Event\CardArchived;
use App\Board\Domain\Event\CardAssigned;
use App\Board\Domain\Event\CardCreated;
use App\Board\Domain\Event\CardFieldChanged;
use App\Board\Domain\Event\CardLabelAdded;
use App\Board\Domain\Event\CardLabelRemoved;
use App\Board\Domain\Event\CardMoved;
use App\Board\Domain\Event\CardRestored;
use App\Board\Domain\Event\CardUnassigned;
use App\Board\Domain\Event\CommentCreated;
use App\Board\Domain\Event\CommentEdited;
use App\Board\Domain\Repository\CardActivityRepository;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class CardActivityListener
{
    public function __construct(private CardActivityRepository $activityRepository)
    {
    }

    #[AsEventListener]
    public function onCardCreated(CardCreated $event): void
    {
        $this->store($event->cardId, $event->actorId, 'card_created', []);
    }

    #[AsEventListener]
    public function onCardFieldChanged(CardFieldChanged $event): void
    {
        $this->store($event->cardId, $event->actorId, 'card_updated', [
            'field' => $event->field,
            'oldValue' => $event->oldValue,
            'newValue' => $event->newValue,
        ]);
    }

    #[AsEventListener]
    public function onCardMoved(CardMoved $event): void
    {
        $this->store($event->cardId, $event->actorId, 'card_moved', [
            'fromColumnId' => (string) $event->oldColumnId,
            'toColumnId' => (string) $event->newColumnId,
            'oldPosition' => $event->oldPosition,
            'newPosition' => $event->newPosition,
        ]);
    }

    #[AsEventListener]
    public function onCardArchived(CardArchived $event): void
    {
        $this->store($event->cardId, $event->actorId, 'card_archived', []);
    }

    #[AsEventListener]
    public function onCardRestored(CardRestored $event): void
    {
        $this->store($event->cardId, $event->actorId, 'card_restored', []);
    }

    #[AsEventListener]
    public function onCardLabelAdded(CardLabelAdded $event): void
    {
        $this->store($event->cardId, $event->actorId, 'label_added', [
            'labelId' => (string) $event->labelId,
            'labelName' => $event->labelName,
        ]);
    }

    #[AsEventListener]
    public function onCardLabelRemoved(CardLabelRemoved $event): void
    {
        $this->store($event->cardId, $event->actorId, 'label_removed', [
            'labelId' => (string) $event->labelId,
            'labelName' => $event->labelName,
        ]);
    }

    #[AsEventListener]
    public function onCardAssigned(CardAssigned $event): void
    {
        $this->store($event->cardId, $event->actorId, 'member_assigned', [
            'userId' => (string) $event->userId,
            'userName' => $event->userName,
        ]);
    }

    #[AsEventListener]
    public function onCardUnassigned(CardUnassigned $event): void
    {
        $this->store($event->cardId, $event->actorId, 'member_unassigned', [
            'userId' => (string) $event->userId,
            'userName' => $event->userName,
        ]);
    }

    #[AsEventListener]
    public function onCommentCreated(CommentCreated $event): void
    {
        $this->store($event->cardId, $event->authorId, 'comment_added', [
            'commentId' => (string) $event->commentId,
        ]);
    }

    #[AsEventListener]
    public function onCommentEdited(CommentEdited $event): void
    {
        $this->store($event->cardId, $event->authorId, 'comment_edited', [
            'commentId' => (string) $event->commentId,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function store(UuidInterface $cardId, ?UuidInterface $actorId, string $type, array $payload): void
    {
        $this->activityRepository->save(CardActivity::create(Uuid::uuid4(), $cardId, $actorId, $type, $payload));
    }
}

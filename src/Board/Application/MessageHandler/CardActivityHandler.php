<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Domain\Entity\CardActivity;
use App\Board\Domain\Event\AttachmentAdded;
use App\Board\Domain\Event\AttachmentRemoved;
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
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final class CardActivityHandler
{
    public function __construct(private CardActivityRepository $activityRepository)
    {
    }

    #[AsMessageHandler]
    public function onCardCreated(CardCreated $event): void
    {
        $this->store($event->cardId, $event->actorId, 'card_created', []);
    }

    #[AsMessageHandler]
    public function onCardFieldChanged(CardFieldChanged $event): void
    {
        $this->store($event->cardId, $event->actorId, 'card_updated', [
            'field' => $event->field,
            'oldValue' => $event->oldValue,
            'newValue' => $event->newValue,
        ]);
    }

    #[AsMessageHandler]
    public function onCardMoved(CardMoved $event): void
    {
        $this->store($event->cardId, $event->actorId, 'card_moved', [
            'fromColumnId' => $event->oldColumnId,
            'toColumnId' => $event->newColumnId,
            'oldPosition' => $event->oldPosition,
            'newPosition' => $event->newPosition,
        ]);
    }

    #[AsMessageHandler]
    public function onCardArchived(CardArchived $event): void
    {
        $this->store($event->cardId, $event->actorId, 'card_archived', []);
    }

    #[AsMessageHandler]
    public function onCardRestored(CardRestored $event): void
    {
        $this->store($event->cardId, $event->actorId, 'card_restored', []);
    }

    #[AsMessageHandler]
    public function onCardLabelAdded(CardLabelAdded $event): void
    {
        $this->store($event->cardId, $event->actorId, 'label_added', [
            'labelId' => $event->labelId,
            'labelName' => $event->labelName,
        ]);
    }

    #[AsMessageHandler]
    public function onCardLabelRemoved(CardLabelRemoved $event): void
    {
        $this->store($event->cardId, $event->actorId, 'label_removed', [
            'labelId' => $event->labelId,
            'labelName' => $event->labelName,
        ]);
    }

    #[AsMessageHandler]
    public function onCardAssigned(CardAssigned $event): void
    {
        $this->store($event->cardId, $event->actorId, 'member_assigned', [
            'userId' => $event->userId,
            'userName' => $event->userName,
        ]);
    }

    #[AsMessageHandler]
    public function onCardUnassigned(CardUnassigned $event): void
    {
        $this->store($event->cardId, $event->actorId, 'member_unassigned', [
            'userId' => $event->userId,
            'userName' => $event->userName,
        ]);
    }

    #[AsMessageHandler]
    public function onCommentCreated(CommentCreated $event): void
    {
        $this->store($event->cardId, $event->authorId, 'comment_added', [
            'commentId' => $event->commentId,
        ]);
    }

    #[AsMessageHandler]
    public function onCommentEdited(CommentEdited $event): void
    {
        $this->store($event->cardId, $event->authorId, 'comment_edited', [
            'commentId' => $event->commentId,
        ]);
    }

    #[AsMessageHandler]
    public function onAttachmentAdded(AttachmentAdded $event): void
    {
        if ($event->commentId !== null) {
            return;
        }

        $this->store($event->cardId, $event->actorId, 'attachment_added', [
            'attachmentId' => $event->attachmentId,
            'fileName' => $event->fileName,
        ]);
    }

    #[AsMessageHandler]
    public function onAttachmentRemoved(AttachmentRemoved $event): void
    {
        $this->store($event->cardId, $event->actorId, 'attachment_removed', [
            'attachmentId' => $event->attachmentId,
            'fileName' => $event->fileName,
            'commentId' => $event->commentId,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function store(string $cardId, ?string $actorId, string $type, array $payload): void
    {
        $this->activityRepository->save(CardActivity::create(
            Uuid::uuid4(),
            Uuid::fromString($cardId),
            $actorId === null ? null : Uuid::fromString($actorId),
            $type,
            $payload,
        ));
    }
}

<?php

declare(strict_types=1);

namespace App\UserEvent\Application\MessageHandler;

use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventInterface;
use App\Board\Domain\Event\CardAssigned;
use App\Board\Domain\Event\CardCreated;
use App\Board\Domain\Event\CardUnassigned;
use App\Board\Domain\Event\CommentCreated;
use App\Project\Domain\Event\ProjectArchived;
use App\Project\Domain\Event\ProjectMemberRoleChanged;
use App\Project\Domain\Event\ProjectRestored;
use App\UserEvent\Application\Port\UserEventContextProvider;
use App\UserEvent\Application\Port\UserEventNotifierInterface;
use App\UserEvent\Application\Service\UserEventView;
use App\UserEvent\Domain\Entity\UserEvent;
use App\UserEvent\Domain\Repository\UserEventRepository;
use App\UserEvent\Domain\Service\RecipientResolver;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final class UserEventFanOutHandler
{
    public function __construct(
        private RecipientResolver $resolver,
        private UserEventRepository $events,
        private UserEventContextProvider $contexts,
        private UserEventNotifierInterface $notifier,
    ) {
    }

    #[AsMessageHandler]
    public function onCardCreated(CardCreated $event): void
    {
        $this->fanOutCard($event, $event->cardId);
    }

    #[AsMessageHandler]
    public function onCardAssigned(CardAssigned $event): void
    {
        $this->fanOutCard($event, $event->cardId);
    }

    #[AsMessageHandler]
    public function onCardUnassigned(CardUnassigned $event): void
    {
        $this->fanOutCard($event, $event->cardId);
    }

    #[AsMessageHandler]
    public function onCommentCreated(CommentCreated $event): void
    {
        $this->fanOutCard($event, $event->cardId);
    }

    #[AsMessageHandler]
    public function onProjectMemberRoleChanged(ProjectMemberRoleChanged $event): void
    {
        $this->fanOutProject($event, $event->projectId, ['role' => $event->newRole->value]);
    }

    #[AsMessageHandler]
    public function onProjectArchived(ProjectArchived $event): void
    {
        $this->fanOutProject($event, $event->projectId);
    }

    #[AsMessageHandler]
    public function onProjectRestored(ProjectRestored $event): void
    {
        $this->fanOutProject($event, $event->projectId);
    }

    private function fanOutCard(DomainEventInterface $event, string $cardIdValue): void
    {
        $cardId = Uuid::fromString($cardIdValue);
        $context = $this->contexts->card($cardId);
        if ($context === null) {
            return;
        }

        $this->persist($event, $context->projectId(), $cardId, [
            'actorName' => $this->actorName($event),
            'projectName' => $context->projectName(),
            'cardTitle' => $context->cardTitle(),
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function fanOutProject(DomainEventInterface $event, string $projectIdValue, array $payload = []): void
    {
        $projectId = Uuid::fromString($projectIdValue);
        $projectName = $this->contexts->projectName($projectId);
        if ($projectName === null) {
            return;
        }

        $this->persist($event, $projectId, null, $payload + [
            'actorName' => $this->actorName($event),
            'projectName' => $projectName,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function persist(DomainEventInterface $event, ?UuidInterface $projectId, ?UuidInterface $cardId, array $payload): void
    {
        $resolved = $this->resolver->resolve($event);
        if ($resolved === null) {
            return;
        }

        $occurredOn = new \DateTimeImmutable($event->getOccurredOn());
        $aggregateRootId = Uuid::fromString($event->getAggregateRootId());
        $actorId = $event->getActorId() === null ? null : Uuid::fromString($event->getActorId());

        foreach ($resolved->userIds() as $userId) {
            if ($this->events->existsByNaturalKey($userId, $resolved->type(), $aggregateRootId, $occurredOn)) {
                continue;
            }

            $userEvent = UserEvent::record(
                Uuid::uuid4(),
                $userId,
                $resolved->type(),
                $aggregateRootId,
                $occurredOn,
                $payload,
                $projectId,
                $cardId,
                $actorId,
            );
            $this->events->save($userEvent);
            $this->notifier->notify($userId, UserEventView::item($userEvent));
        }
    }

    private function actorName(DomainEventInterface $event): ?string
    {
        $actorId = $event->getActorId();

        return $actorId === null ? null : $this->contexts->userName(Uuid::fromString($actorId));
    }
}

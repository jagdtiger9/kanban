<?php

declare(strict_types=1);

namespace App\Board\Domain\Entity;

use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventAwareInterface;
use Aljerom\SymfonyBoilerplate\DomainEvents\EventStoreTrait;
use App\Board\Domain\Event\CardArchived;
use App\Board\Domain\Event\CardAssigned;
use App\Board\Domain\Event\CardChecklistChanged;
use App\Board\Domain\Event\CardCompleted;
use App\Board\Domain\Event\CardCreated;
use App\Board\Domain\Event\CardExecutorAssigned;
use App\Board\Domain\Event\CardExecutorUnassigned;
use App\Board\Domain\Event\CardFieldChanged;
use App\Board\Domain\Event\CardLabelAdded;
use App\Board\Domain\Event\CardLabelRemoved;
use App\Board\Domain\Event\CardMoved;
use App\Board\Domain\Event\CardRecurrenceChanged;
use App\Board\Domain\Event\CardRestored;
use App\Board\Domain\Event\CardUnassigned;
use App\Board\Domain\Event\CardUncompleted;
use App\Board\Domain\Exception\CardLinkException;
use App\Board\Domain\Exception\ChecklistItemNotFoundException;
use App\Board\Domain\Exception\ChecklistLimitException;
use App\Board\Domain\ValueObject\RecurrenceRule;
use App\User\Domain\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'cards')]
#[ORM\Index(name: 'idx_cards_column_position', columns: ['column_id', 'position'])]
final class Card implements DomainEventAwareInterface
{
    use EventStoreTrait;

    public const CHECKLIST_MAX_ITEMS = 100;
    public const CHECKLIST_TEXT_MAX_LENGTH = 500;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Column(type: 'uuid')]
    private UuidInterface $columnId;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    #[ORM\Column(type: Types::INTEGER)]
    private int $position;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dueDate;

    #[ORM\Column(type: 'uuid')]
    private UuidInterface $creatorId;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?UuidInterface $executorId;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?UuidInterface $linkedCardId;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $recurrence;

    private ?RecurrenceRule $recurrenceRule = null;

    /** @var list<array{id: string, text: string, done: bool}>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $checklist;

    #[ORM\Column(type: 'datetimetz_micro', columnDefinition: 'TIMESTAMP(6) WITH TIME ZONE')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_micro', columnDefinition: 'TIMESTAMP(6) WITH TIME ZONE')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'datetimetz_micro', columnDefinition: 'TIMESTAMP(6) WITH TIME ZONE', nullable: true)]
    private ?\DateTimeImmutable $archivedAt;

    #[ORM\Column(type: 'datetimetz_micro', columnDefinition: 'TIMESTAMP(6) WITH TIME ZONE', nullable: true)]
    private ?\DateTimeImmutable $completedAt;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?UuidInterface $completedBy;

    /** @var Collection<int, Label> */
    #[ORM\ManyToMany(targetEntity: Label::class)]
    #[ORM\JoinTable(name: 'card_labels')]
    #[ORM\JoinColumn(name: 'card_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'label_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $labels;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'card_assignees')]
    #[ORM\JoinColumn(name: 'card_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $assignees;

    private function __construct(
        UuidInterface $id,
        UuidInterface $columnId,
        UuidInterface $creatorId,
        string $title,
        string $description,
        int $position,
        ?\DateTimeImmutable $dueDate,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->columnId = $columnId;
        $this->creatorId = $creatorId;
        $this->executorId = null;
        $this->linkedCardId = null;
        $this->recurrence = null;
        $this->checklist = null;
        $this->title = $title;
        $this->description = $description;
        $this->position = $position;
        $this->dueDate = $dueDate;
        $this->createdAt = $createdAt;
        $this->updatedAt = $createdAt;
        $this->archivedAt = null;
        $this->completedAt = null;
        $this->completedBy = null;
        $this->labels = new ArrayCollection();
        $this->assignees = new ArrayCollection();
    }

    public static function create(
        UuidInterface $id,
        UuidInterface $columnId,
        UuidInterface $creatorId,
        string $title,
        string $description,
        int $position,
        ?\DateTimeImmutable $dueDate = null,
    ): self {
        $title = trim($title);
        if ($title === '') {
            throw new \InvalidArgumentException('Card title must not be empty');
        }
        if ($position < 0) {
            throw new \InvalidArgumentException('Card position must not be negative');
        }

        $card = new self($id, $columnId, $creatorId, $title, $description, $position, $dueDate, new \DateTimeImmutable());
        $card->recordEvent(new CardCreated($id->toString(), $creatorId->toString()));

        return $card;
    }

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function columnId(): UuidInterface
    {
        return $this->columnId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function dueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function creatorId(): UuidInterface
    {
        return $this->creatorId;
    }

    public function executorId(): ?UuidInterface
    {
        return $this->executorId;
    }

    public function linkedCardId(): ?UuidInterface
    {
        return $this->linkedCardId;
    }

    public function recurrence(): ?RecurrenceRule
    {
        if ($this->recurrenceRule === null && $this->recurrence !== null) {
            $this->recurrenceRule = RecurrenceRule::fromArray($this->recurrence);
        }

        return $this->recurrenceRule;
    }

    /** @return list<array{id: string, text: string, done: bool}> */
    public function checklist(): array
    {
        return $this->checklist ?? [];
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function archivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }

    public function completedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function completedBy(): ?UuidInterface
    {
        return $this->completedBy;
    }

    /** @return Collection<int, Label> */
    public function labels(): Collection
    {
        return $this->labels;
    }

    /** @return Collection<int, User> */
    public function assignees(): Collection
    {
        return $this->assignees;
    }

    public function changeTitle(string $title, ?UuidInterface $actorId = null): void
    {
        $title = trim($title);
        if ($title === '') {
            throw new \InvalidArgumentException('Card title must not be empty');
        }
        if ($title === $this->title) {
            return;
        }

        $old = $this->title;
        $this->title = $title;
        $this->touch();
        $this->recordEvent(new CardFieldChanged($this->id->toString(), 'title', $old, $title, $actorId?->toString()));
    }

    public function changeDescription(string $description, ?UuidInterface $actorId = null): void
    {
        if ($description === $this->description) {
            return;
        }

        $old = $this->description;
        $this->description = $description;
        $this->touch();
        $this->recordEvent(new CardFieldChanged($this->id->toString(), 'description', $old, $description, $actorId?->toString()));
    }

    public function changeDueDate(?\DateTimeImmutable $dueDate, ?UuidInterface $actorId = null): void
    {
        if ($dueDate !== null) {
            $this->clearRecurrence($actorId);
        }

        $new = $dueDate?->format('Y-m-d');
        $old = $this->dueDate?->format('Y-m-d');
        if ($new === $old) {
            return;
        }

        $this->dueDate = $dueDate;
        $this->touch();
        $this->recordEvent(new CardFieldChanged($this->id->toString(), 'due_date', $old, $new, $actorId?->toString()));
    }

    public function changeLinkedCard(?UuidInterface $linkedCardId, ?UuidInterface $actorId = null): void
    {
        if ($linkedCardId !== null && $linkedCardId->equals($this->id)) {
            throw new CardLinkException('A card cannot be linked to itself');
        }

        $new = $linkedCardId?->toString();
        $old = $this->linkedCardId?->toString();
        if ($new === $old) {
            return;
        }

        $this->linkedCardId = $linkedCardId;
        $this->touch();
        $this->recordEvent(new CardFieldChanged($this->id->toString(), 'linked_card', $old, $new, $actorId?->toString()));
    }

    public function changeRecurrence(?RecurrenceRule $recurrence, ?UuidInterface $actorId = null): void
    {
        if ($recurrence !== null) {
            $this->clearDueDate($actorId);
        }

        $old = $this->recurrence();
        if ($old === null && $recurrence === null) {
            return;
        }
        if ($old !== null && $recurrence !== null && $old->equals($recurrence)) {
            return;
        }

        $this->recurrence = $recurrence?->toArray();
        $this->recurrenceRule = $recurrence;
        $this->touch();
        $this->recordEvent(new CardRecurrenceChanged(
            $this->id->toString(),
            $old?->kind()->value,
            $recurrence?->kind()->value,
            $actorId?->toString(),
        ));
    }

    public function addChecklistItem(UuidInterface $itemId, string $text, ?UuidInterface $actorId = null): void
    {
        $text = trim($text);
        if ($text === '') {
            throw new \InvalidArgumentException('Checklist item text must not be empty');
        }
        if (mb_strlen($text) > self::CHECKLIST_TEXT_MAX_LENGTH) {
            throw new \InvalidArgumentException(sprintf('Checklist item text must not exceed %d characters', self::CHECKLIST_TEXT_MAX_LENGTH));
        }

        $checklist = $this->checklist();
        if (count($checklist) >= self::CHECKLIST_MAX_ITEMS) {
            throw new ChecklistLimitException(sprintf('A card cannot have more than %d checklist items', self::CHECKLIST_MAX_ITEMS));
        }

        $checklist[] = ['id' => $itemId->toString(), 'text' => $text, 'done' => false];
        $this->checklist = $checklist;
        $this->touch();
        $this->recordEvent(new CardChecklistChanged(
            $this->id->toString(),
            CardChecklistChanged::ACTION_ADDED,
            $itemId->toString(),
            $text,
            false,
            $actorId?->toString(),
        ));
    }

    public function setChecklistItemDone(UuidInterface $itemId, bool $done, ?UuidInterface $actorId = null): void
    {
        $checklist = $this->checklist();
        foreach ($checklist as $index => $item) {
            if ($item['id'] !== $itemId->toString()) {
                continue;
            }
            if ($item['done'] === $done) {
                return;
            }

            $checklist[$index]['done'] = $done;
            $this->checklist = $checklist;
            $this->touch();
            $this->recordEvent(new CardChecklistChanged(
                $this->id->toString(),
                CardChecklistChanged::ACTION_TOGGLED,
                $item['id'],
                $item['text'],
                $done,
                $actorId?->toString(),
            ));

            return;
        }

        throw new ChecklistItemNotFoundException(sprintf('Checklist item "%s" not found', $itemId->toString()));
    }

    public function removeChecklistItem(UuidInterface $itemId, ?UuidInterface $actorId = null): void
    {
        $checklist = $this->checklist();
        foreach ($checklist as $index => $item) {
            if ($item['id'] !== $itemId->toString()) {
                continue;
            }

            array_splice($checklist, $index, 1);
            $this->checklist = $checklist;
            $this->touch();
            $this->recordEvent(new CardChecklistChanged(
                $this->id->toString(),
                CardChecklistChanged::ACTION_REMOVED,
                $item['id'],
                $item['text'],
                false,
                $actorId?->toString(),
            ));

            return;
        }

        throw new ChecklistItemNotFoundException(sprintf('Checklist item "%s" not found', $itemId->toString()));
    }

    public function moveTo(UuidInterface $newColumnId, int $newPosition, ?UuidInterface $actorId = null): void
    {
        if ($newPosition < 0) {
            throw new \InvalidArgumentException('Card position must not be negative');
        }
        if ($this->columnId->equals($newColumnId) && $this->position === $newPosition) {
            return;
        }

        $this->recordEvent(new CardMoved(
            $this->id->toString(),
            $this->columnId->toString(),
            $newColumnId->toString(),
            $this->position,
            $newPosition,
            $actorId?->toString(),
        ));
        $this->columnId = $newColumnId;
        $this->position = $newPosition;
    }

    public function changePosition(int $position): void
    {
        if ($position < 0) {
            throw new \InvalidArgumentException('Card position must not be negative');
        }

        $this->position = $position;
    }

    public function archive(?UuidInterface $actorId = null): void
    {
        if ($this->archivedAt !== null) {
            return;
        }

        $this->archivedAt = new \DateTimeImmutable();
        $this->recordEvent(new CardArchived($this->id->toString(), $actorId?->toString()));
    }

    public function restore(?UuidInterface $actorId = null): void
    {
        if ($this->archivedAt === null) {
            return;
        }

        $this->archivedAt = null;
        $this->recordEvent(new CardRestored($this->id->toString(), $actorId?->toString()));
    }

    public function complete(?UuidInterface $actorId = null): void
    {
        if ($this->completedAt !== null) {
            return;
        }

        $this->completedAt = new \DateTimeImmutable();
        $this->completedBy = $actorId;
        $this->recordEvent(new CardCompleted($this->id->toString(), $actorId?->toString()));
    }

    public function uncomplete(?UuidInterface $actorId = null): void
    {
        if ($this->completedAt === null) {
            return;
        }

        $this->completedAt = null;
        $this->completedBy = null;
        $this->recordEvent(new CardUncompleted($this->id->toString(), $actorId?->toString()));
    }

    public function addLabel(Label $label, ?UuidInterface $actorId = null): void
    {
        if ($this->labels->contains($label)) {
            return;
        }

        $this->labels->add($label);
        $this->recordEvent(new CardLabelAdded($this->id->toString(), $label->id()->toString(), $label->name(), $actorId?->toString()));
    }

    public function removeLabel(Label $label, ?UuidInterface $actorId = null): void
    {
        if (!$this->labels->contains($label)) {
            return;
        }

        $this->labels->removeElement($label);
        $this->recordEvent(new CardLabelRemoved($this->id->toString(), $label->id()->toString(), $label->name(), $actorId?->toString()));
    }

    public function assign(User $user, ?UuidInterface $actorId = null): void
    {
        if ($this->assignees->contains($user)) {
            return;
        }

        $this->assignees->add($user);
        $this->recordEvent(new CardAssigned($this->id->toString(), $user->id()->toString(), $user->displayName(), $actorId?->toString()));
    }

    public function unassign(User $user, ?UuidInterface $actorId = null): void
    {
        if (!$this->assignees->contains($user)) {
            return;
        }

        $this->assignees->removeElement($user);
        $this->recordEvent(new CardUnassigned($this->id->toString(), $user->id()->toString(), $user->displayName(), $actorId?->toString()));
    }

    public function assignExecutor(User $user, ?UuidInterface $actorId = null): void
    {
        if ($this->executorId !== null && $this->executorId->equals($user->id())) {
            return;
        }

        $this->executorId = $user->id();
        $this->recordEvent(new CardExecutorAssigned($this->id->toString(), $user->id()->toString(), $user->displayName(), $actorId?->toString()));
    }

    public function unassignExecutor(User $user, ?UuidInterface $actorId = null): void
    {
        if ($this->executorId === null || !$this->executorId->equals($user->id())) {
            return;
        }

        $this->executorId = null;
        $this->recordEvent(new CardExecutorUnassigned($this->id->toString(), $user->id()->toString(), $user->displayName(), $actorId?->toString()));
    }

    private function clearDueDate(?UuidInterface $actorId): void
    {
        if ($this->dueDate === null) {
            return;
        }

        $old = $this->dueDate->format('Y-m-d');
        $this->dueDate = null;
        $this->touch();
        $this->recordEvent(new CardFieldChanged($this->id->toString(), 'due_date', $old, null, $actorId?->toString()));
    }

    private function clearRecurrence(?UuidInterface $actorId): void
    {
        $rule = $this->recurrence();
        if ($rule === null) {
            return;
        }

        $this->recurrence = null;
        $this->recurrenceRule = null;
        $this->touch();
        $this->recordEvent(new CardRecurrenceChanged($this->id->toString(), $rule->kind()->value, null, $actorId?->toString()));
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}

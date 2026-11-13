<?php

declare(strict_types=1);

namespace App\Board\Domain\Entity;

use App\Board\Domain\Event\CardArchived;
use App\Board\Domain\Event\CardAssigned;
use App\Board\Domain\Event\CardFieldChanged;
use App\Board\Domain\Event\CardLabelAdded;
use App\Board\Domain\Event\CardLabelRemoved;
use App\Board\Domain\Event\CardMoved;
use App\Board\Domain\Event\CardRestored;
use App\Board\Domain\Event\CardUnassigned;
use App\User\Domain\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'cards')]
final class Card
{
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

    #[ORM\Column(type: 'datetimetz_micro', columnDefinition: 'TIMESTAMP(6) WITH TIME ZONE')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_micro', columnDefinition: 'TIMESTAMP(6) WITH TIME ZONE')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'datetimetz_micro', columnDefinition: 'TIMESTAMP(6) WITH TIME ZONE', nullable: true)]
    private ?\DateTimeImmutable $archivedAt;

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
        $this->title = $title;
        $this->description = $description;
        $this->position = $position;
        $this->dueDate = $dueDate;
        $this->createdAt = $createdAt;
        $this->updatedAt = $createdAt;
        $this->archivedAt = null;
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

        return new self($id, $columnId, $creatorId, $title, $description, $position, $dueDate, new \DateTimeImmutable());
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

    public function changeTitle(string $title, ?UuidInterface $actorId = null): ?CardFieldChanged
    {
        $title = trim($title);
        if ($title === '') {
            throw new \InvalidArgumentException('Card title must not be empty');
        }
        if ($title === $this->title) {
            return null;
        }

        $old = $this->title;
        $this->title = $title;
        $this->touch();

        return new CardFieldChanged($this->id, 'title', $old, $title, $actorId);
    }

    public function changeDescription(string $description, ?UuidInterface $actorId = null): ?CardFieldChanged
    {
        if ($description === $this->description) {
            return null;
        }

        $old = $this->description;
        $this->description = $description;
        $this->touch();

        return new CardFieldChanged($this->id, 'description', $old, $description, $actorId);
    }

    public function changeDueDate(?\DateTimeImmutable $dueDate, ?UuidInterface $actorId = null): ?CardFieldChanged
    {
        $new = $dueDate?->format('Y-m-d');
        $old = $this->dueDate?->format('Y-m-d');
        if ($new === $old) {
            return null;
        }

        $this->dueDate = $dueDate;
        $this->touch();

        return new CardFieldChanged($this->id, 'due_date', $old, $new, $actorId);
    }

    public function moveTo(UuidInterface $newColumnId, int $newPosition, ?UuidInterface $actorId = null): ?CardMoved
    {
        if ($newPosition < 0) {
            throw new \InvalidArgumentException('Card position must not be negative');
        }
        if ($this->columnId->equals($newColumnId) && $this->position === $newPosition) {
            return null;
        }

        $event = new CardMoved($this->id, $this->columnId, $newColumnId, $this->position, $newPosition, $actorId);
        $this->columnId = $newColumnId;
        $this->position = $newPosition;

        return $event;
    }

    public function changePosition(int $position): void
    {
        if ($position < 0) {
            throw new \InvalidArgumentException('Card position must not be negative');
        }

        $this->position = $position;
    }

    public function archive(?UuidInterface $actorId = null): ?CardArchived
    {
        if ($this->archivedAt !== null) {
            return null;
        }

        $this->archivedAt = new \DateTimeImmutable();

        return new CardArchived($this->id, $actorId);
    }

    public function restore(?UuidInterface $actorId = null): ?CardRestored
    {
        if ($this->archivedAt === null) {
            return null;
        }

        $this->archivedAt = null;

        return new CardRestored($this->id, $actorId);
    }

    public function addLabel(Label $label, ?UuidInterface $actorId = null): ?CardLabelAdded
    {
        if ($this->labels->contains($label)) {
            return null;
        }

        $this->labels->add($label);

        return new CardLabelAdded($this->id, $label->id(), $label->name(), $actorId);
    }

    public function removeLabel(Label $label, ?UuidInterface $actorId = null): ?CardLabelRemoved
    {
        if (!$this->labels->contains($label)) {
            return null;
        }

        $this->labels->removeElement($label);

        return new CardLabelRemoved($this->id, $label->id(), $label->name(), $actorId);
    }

    public function assign(User $user, ?UuidInterface $actorId = null): ?CardAssigned
    {
        if ($this->assignees->contains($user)) {
            return null;
        }

        $this->assignees->add($user);

        return new CardAssigned($this->id, $user->id(), $user->displayName(), $actorId);
    }

    public function unassign(User $user, ?UuidInterface $actorId = null): ?CardUnassigned
    {
        if (!$this->assignees->contains($user)) {
            return null;
        }

        $this->assignees->removeElement($user);

        return new CardUnassigned($this->id, $user->id(), $user->displayName(), $actorId);
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}

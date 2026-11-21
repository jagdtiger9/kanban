<?php

declare(strict_types=1);

namespace App\Board\Domain\Entity;

use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventAwareInterface;
use Aljerom\SymfonyBoilerplate\DomainEvents\EventStoreTrait;
use App\Board\Domain\Event\CommentCreated;
use App\Board\Domain\Event\CommentDeleted;
use App\Board\Domain\Event\CommentEdited;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'comments')]
#[ORM\Index(name: 'idx_comments_card_created', columns: ['card_id', 'created_at', 'id'])]
final class Comment implements DomainEventAwareInterface
{
    use EventStoreTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Column(type: 'uuid')]
    private UuidInterface $cardId;

    #[ORM\Column(type: 'uuid')]
    private UuidInterface $authorId;

    #[ORM\Column(type: Types::TEXT)]
    private string $body;

    #[ORM\Column(type: 'datetimetz_micro', columnDefinition: 'TIMESTAMP(6) WITH TIME ZONE')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_micro', columnDefinition: 'TIMESTAMP(6) WITH TIME ZONE')]
    private \DateTimeImmutable $updatedAt;

    private function __construct(
        UuidInterface $id,
        UuidInterface $cardId,
        UuidInterface $authorId,
        string $body,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->cardId = $cardId;
        $this->authorId = $authorId;
        $this->body = $body;
        $this->createdAt = $createdAt;
        $this->updatedAt = $createdAt;
    }

    public static function create(UuidInterface $id, UuidInterface $cardId, UuidInterface $authorId, string $body): self
    {
        $body = trim($body);
        if ($body === '') {
            throw new \InvalidArgumentException('Comment body must not be empty');
        }

        $comment = new self($id, $cardId, $authorId, $body, new \DateTimeImmutable());
        $comment->recordEvent(new CommentCreated($cardId->toString(), $id->toString(), $authorId->toString()));

        return $comment;
    }

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function cardId(): UuidInterface
    {
        return $this->cardId;
    }

    public function authorId(): UuidInterface
    {
        return $this->authorId;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function edit(string $body): void
    {
        $body = trim($body);
        if ($body === '') {
            throw new \InvalidArgumentException('Comment body must not be empty');
        }
        if ($body === $this->body) {
            return;
        }

        $this->body = $body;
        $this->updatedAt = new \DateTimeImmutable();
        $this->recordEvent(new CommentEdited($this->cardId->toString(), $this->id->toString(), $this->authorId->toString()));
    }

    public function delete(?UuidInterface $actorId = null): void
    {
        $this->recordEvent(new CommentDeleted($this->cardId->toString(), $this->id->toString(), $actorId?->toString()));
    }
}

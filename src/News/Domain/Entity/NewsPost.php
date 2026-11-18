<?php

declare(strict_types=1);

namespace App\News\Domain\Entity;

use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventAwareInterface;
use Aljerom\SymfonyBoilerplate\DomainEvents\EventStoreTrait;
use App\News\Domain\Event\NewsPostCreated;
use App\News\Domain\Event\NewsPostDeleted;
use App\News\Domain\Event\NewsPostEdited;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'news_posts')]
#[ORM\Index(columns: ['created_at'], name: 'idx_news_posts_created_at')]
final class NewsPost implements DomainEventAwareInterface
{
    use EventStoreTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

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
        UuidInterface $authorId,
        string $body,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->authorId = $authorId;
        $this->body = $body;
        $this->createdAt = $createdAt;
        $this->updatedAt = $createdAt;
    }

    public static function create(UuidInterface $id, UuidInterface $authorId, string $body): self
    {
        $body = trim($body);
        if ($body === '') {
            throw new \InvalidArgumentException('News post body must not be empty');
        }

        $post = new self($id, $authorId, $body, new \DateTimeImmutable());
        $post->recordEvent(new NewsPostCreated($id->toString(), $authorId->toString()));

        return $post;
    }

    public function id(): UuidInterface
    {
        return $this->id;
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
            throw new \InvalidArgumentException('News post body must not be empty');
        }
        if ($body === $this->body) {
            return;
        }

        $this->body = $body;
        $this->updatedAt = new \DateTimeImmutable();
        $this->recordEvent(new NewsPostEdited($this->id->toString(), $this->authorId->toString()));
    }

    public function delete(?UuidInterface $actorId = null): void
    {
        $this->recordEvent(new NewsPostDeleted($this->id->toString(), $actorId?->toString()));
    }
}

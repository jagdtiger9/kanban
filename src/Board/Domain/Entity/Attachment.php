<?php

declare(strict_types=1);

namespace App\Board\Domain\Entity;

use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventAwareInterface;
use Aljerom\SymfonyBoilerplate\DomainEvents\EventStoreTrait;
use App\Board\Domain\Event\AttachmentAdded;
use App\Board\Domain\Event\AttachmentRemoved;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'board_attachments')]
#[ORM\Index(name: 'board_attachments_card_idx', columns: ['card_id'])]
#[ORM\Index(name: 'board_attachments_comment_idx', columns: ['comment_id'])]
final class Attachment implements DomainEventAwareInterface
{
    use EventStoreTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Column(type: 'uuid')]
    private UuidInterface $cardId;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?UuidInterface $commentId;

    #[ORM\Column(type: 'uuid')]
    private UuidInterface $uploaderId;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $originalName;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    private string $storageKey;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true, nullable: true)]
    private ?string $thumbnailStorageKey;

    #[ORM\Column(type: Types::STRING, length: 127)]
    private string $mimeType;

    #[ORM\Column(type: Types::BIGINT)]
    private int $sizeBytes;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $width;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $height;

    #[ORM\Column(type: 'datetimetz_micro', columnDefinition: 'TIMESTAMP(6) WITH TIME ZONE')]
    private \DateTimeImmutable $createdAt;

    private function __construct(
        UuidInterface $id,
        UuidInterface $cardId,
        ?UuidInterface $commentId,
        UuidInterface $uploaderId,
        string $originalName,
        string $storageKey,
        ?string $thumbnailStorageKey,
        string $mimeType,
        int $sizeBytes,
        ?int $width,
        ?int $height,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->cardId = $cardId;
        $this->commentId = $commentId;
        $this->uploaderId = $uploaderId;
        $this->originalName = $originalName;
        $this->storageKey = $storageKey;
        $this->thumbnailStorageKey = $thumbnailStorageKey;
        $this->mimeType = $mimeType;
        $this->sizeBytes = $sizeBytes;
        $this->width = $width;
        $this->height = $height;
        $this->createdAt = $createdAt;
    }

    public static function create(
        UuidInterface $id,
        UuidInterface $cardId,
        ?UuidInterface $commentId,
        UuidInterface $uploaderId,
        string $originalName,
        string $storageKey,
        ?string $thumbnailStorageKey,
        string $mimeType,
        int $sizeBytes,
        ?int $width = null,
        ?int $height = null,
    ): self {
        $originalName = trim($originalName);
        if ($originalName === '' || mb_strlen($originalName) > 255) {
            throw new \InvalidArgumentException('Attachment file name must be between 1 and 255 characters');
        }

        $storageKey = trim($storageKey);
        if ($storageKey === '' || strlen($storageKey) > 255) {
            throw new \InvalidArgumentException('Attachment storage key must be between 1 and 255 characters');
        }

        if ($thumbnailStorageKey !== null) {
            $thumbnailStorageKey = trim($thumbnailStorageKey);
            if ($thumbnailStorageKey === '' || strlen($thumbnailStorageKey) > 255) {
                throw new \InvalidArgumentException('Attachment thumbnail key must be between 1 and 255 characters');
            }
        }

        $mimeType = trim($mimeType);
        if ($mimeType === '' || strlen($mimeType) > 127) {
            throw new \InvalidArgumentException('Attachment MIME type must be between 1 and 127 characters');
        }

        if ($sizeBytes <= 0) {
            throw new \InvalidArgumentException('Attachment size must be positive');
        }

        if (($width === null) !== ($height === null) || ($width !== null && ($width <= 0 || $height <= 0))) {
            throw new \InvalidArgumentException('Attachment dimensions must both be positive or both be absent');
        }

        $attachment = new self(
            $id,
            $cardId,
            $commentId,
            $uploaderId,
            $originalName,
            $storageKey,
            $thumbnailStorageKey,
            $mimeType,
            $sizeBytes,
            $width,
            $height,
            new \DateTimeImmutable(),
        );
        $attachment->recordEvent(
            new AttachmentAdded(
                $id->toString(),
                $cardId->toString(),
                $commentId?->toString(),
                $originalName,
                $uploaderId->toString(),
            )
        );

        return $attachment;
    }

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function cardId(): UuidInterface
    {
        return $this->cardId;
    }

    public function commentId(): ?UuidInterface
    {
        return $this->commentId;
    }

    public function isCommentAttachment(): bool
    {
        return $this->commentId !== null;
    }

    public function uploaderId(): UuidInterface
    {
        return $this->uploaderId;
    }

    public function originalName(): string
    {
        return $this->originalName;
    }

    public function storageKey(): string
    {
        return $this->storageKey;
    }

    public function thumbnailStorageKey(): ?string
    {
        return $this->thumbnailStorageKey;
    }

    public function mimeType(): string
    {
        return $this->mimeType;
    }

    public function sizeBytes(): int
    {
        return $this->sizeBytes;
    }

    public function width(): ?int
    {
        return $this->width;
    }

    public function height(): ?int
    {
        return $this->height;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function remove(?UuidInterface $actorId = null): void
    {
        $this->recordEvent(
            new AttachmentRemoved(
                $this->id->toString(),
                $this->cardId->toString(),
                $this->commentId?->toString(),
                $this->originalName,
                $this->storageKey,
                $this->thumbnailStorageKey,
                $actorId?->toString(),
            )
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Domain\Entity;

use App\Board\Domain\Entity\Comment;
use App\Board\Domain\Event\CommentEdited;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class CommentTest extends TestCase
{
    private function createComment(string $body = 'Hello'): Comment
    {
        $comment = Comment::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), $body);
        $comment->releaseEvents();

        return $comment;
    }

    public function testCreateTrimsBody(): void
    {
        $comment = $this->createComment('  Hello  ');

        self::assertSame('Hello', $comment->body());
    }

    public function testCreateRejectsEmptyBody(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->createComment('   ');
    }

    public function testEditRecordsEventAndUpdatesTimestamp(): void
    {
        $comment = $this->createComment();

        $comment->edit('Edited');

        $events = $comment->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CommentEdited::class, $events[0]);
        self::assertSame($comment->id()->toString(), $events[0]->commentId);
        self::assertSame('Edited', $comment->body());
        self::assertGreaterThanOrEqual($comment->createdAt(), $comment->updatedAt());
    }

    public function testEditToSameBodyRecordsNoEvent(): void
    {
        $comment = $this->createComment();

        $comment->edit('Hello');

        self::assertSame([], $comment->releaseEvents());
    }

    public function testEditRejectsEmptyBody(): void
    {
        $comment = $this->createComment();

        $this->expectException(\InvalidArgumentException::class);
        $comment->edit('  ');
    }
}

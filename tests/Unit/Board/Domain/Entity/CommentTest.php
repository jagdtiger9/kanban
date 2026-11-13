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
        return Comment::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), $body);
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

    public function testEditEmitsEventAndUpdatesTimestamp(): void
    {
        $comment = $this->createComment();

        $event = $comment->edit('Edited');

        self::assertInstanceOf(CommentEdited::class, $event);
        self::assertTrue($event->commentId->equals($comment->id()));
        self::assertSame('Edited', $comment->body());
        self::assertGreaterThanOrEqual($comment->createdAt(), $comment->updatedAt());
    }

    public function testEditToSameBodyReturnsNull(): void
    {
        $comment = $this->createComment();

        self::assertNull($comment->edit('Hello'));
    }

    public function testEditRejectsEmptyBody(): void
    {
        $comment = $this->createComment();

        $this->expectException(\InvalidArgumentException::class);
        $comment->edit('  ');
    }
}

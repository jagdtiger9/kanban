<?php

declare(strict_types=1);

namespace App\Tests\Unit\News\Domain\Entity;

use App\News\Domain\Entity\NewsPost;
use App\News\Domain\Event\NewsPostCreated;
use App\News\Domain\Event\NewsPostDeleted;
use App\News\Domain\Event\NewsPostEdited;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class NewsPostTest extends TestCase
{
    private function createPost(string $body = 'Hello'): NewsPost
    {
        $post = NewsPost::create(Uuid::uuid4(), Uuid::uuid4(), $body);
        $post->releaseEvents();

        return $post;
    }

    public function testCreateTrimsBodyAndRecordsEvent(): void
    {
        $id = Uuid::uuid4();
        $authorId = Uuid::uuid4();

        $post = NewsPost::create($id, $authorId, '  Hello  ');
        $events = $post->releaseEvents();

        self::assertSame('Hello', $post->body());
        self::assertCount(1, $events);
        self::assertInstanceOf(NewsPostCreated::class, $events[0]);
        self::assertSame($id->toString(), $events[0]->newsPostId);
        self::assertSame($authorId->toString(), $events[0]->authorId);
        self::assertSame($post->createdAt(), $post->updatedAt());
    }

    public function testCreateRejectsEmptyBody(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        NewsPost::create(Uuid::uuid4(), Uuid::uuid4(), '   ');
    }

    public function testEditRecordsEventAndUpdatesTimestamp(): void
    {
        $post = $this->createPost();

        $post->edit('Edited');

        $events = $post->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(NewsPostEdited::class, $events[0]);
        self::assertSame($post->id()->toString(), $events[0]->newsPostId);
        self::assertSame($post->authorId()->toString(), $events[0]->authorId);
        self::assertSame('Edited', $post->body());
        self::assertGreaterThanOrEqual($post->createdAt(), $post->updatedAt());
    }

    public function testEditToSameBodyRecordsNoEvent(): void
    {
        $post = $this->createPost();

        $post->edit('Hello');

        self::assertSame([], $post->releaseEvents());
    }

    public function testEditRejectsEmptyBody(): void
    {
        $post = $this->createPost();

        $this->expectException(\InvalidArgumentException::class);
        $post->edit('  ');
    }

    public function testDeleteRecordsEvent(): void
    {
        $post = $this->createPost();
        $actorId = Uuid::uuid4();

        $post->delete($actorId);

        $events = $post->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(NewsPostDeleted::class, $events[0]);
        self::assertSame($post->id()->toString(), $events[0]->newsPostId);
    }
}

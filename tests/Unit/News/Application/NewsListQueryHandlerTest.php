<?php

declare(strict_types=1);

namespace App\Tests\Unit\News\Application;

use App\News\Application\Message\Query\NewsListQuery;
use App\News\Application\MessageHandler\NewsListQueryHandler;
use App\News\Domain\Entity\NewsPost;
use App\News\Domain\Repository\NewsPostRepository;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class NewsListQueryHandlerTest extends TestCase
{
    public function testReturnsPostsWithDeduplicatedAuthors(): void
    {
        $author = User::create(Uuid::uuid4(), 'author@example.com', 'hash', 'Author');
        $other = User::create(Uuid::uuid4(), 'other@example.com', 'hash', 'Other');
        $first = NewsPost::create(Uuid::uuid4(), $author->id(), 'First');
        $second = NewsPost::create(Uuid::uuid4(), $author->id(), 'Second');
        $third = NewsPost::create(Uuid::uuid4(), $other->id(), 'Third');

        $postRepo = $this->createMock(NewsPostRepository::class);
        $postRepo->expects(self::once())
            ->method('findPage')
            ->with(2, 10)
            ->willReturn([$first, $second, $third]);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->expects(self::exactly(2))->method('findById')->willReturnCallback(
            fn($id) => (string) $id === (string) $author->id() ? $author : $other,
        );

        $result = (new NewsListQueryHandler($postRepo, $userRepo))->__invoke(new NewsListQuery(2, 10));

        self::assertSame([$first, $second, $third], $result['posts']);
        self::assertSame(
            [(string) $author->id() => $author, (string) $other->id() => $other],
            $result['authors'],
        );
    }

    public function testMissingAuthorIsAnInvariantViolation(): void
    {
        $post = NewsPost::create(Uuid::uuid4(), Uuid::uuid4(), 'First');

        $postRepo = $this->createMock(NewsPostRepository::class);
        $postRepo->method('findPage')->willReturn([$post]);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findById')->willReturn(null);

        $this->expectException(\LogicException::class);
        (new NewsListQueryHandler($postRepo, $userRepo))->__invoke(new NewsListQuery());
    }
}

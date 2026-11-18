<?php

declare(strict_types=1);

namespace App\Tests\Unit\News\Application;

use App\News\Application\Message\Command\DeleteNewsPostCommand;
use App\News\Application\MessageHandler\DeleteNewsPostCommandHandler;
use App\News\Application\Service\NewsGuard;
use App\News\Domain\Entity\NewsPost;
use App\News\Domain\Exception\NewsPostForbiddenException;
use App\News\Domain\Exception\NewsPostNotFoundException;
use App\News\Domain\Repository\NewsPostRepository;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class DeleteNewsPostCommandHandlerTest extends TestCase
{
    public function testAuthorDeletesPost(): void
    {
        $author = User::create(Uuid::uuid4(), 'author@example.com', 'hash', 'Author');
        $post = NewsPost::create(Uuid::uuid4(), $author->id(), 'Hello');
        $post->releaseEvents();

        $postRepo = $this->createMock(NewsPostRepository::class);
        $postRepo->method('findById')->willReturn($post);
        $postRepo->expects(self::once())->method('remove')->with($post);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findById')->willReturn($author);

        (new DeleteNewsPostCommandHandler($postRepo, new NewsGuard($userRepo)))
            ->__invoke(new DeleteNewsPostCommand((string) $post->id(), (string) $author->id()));

        $events = $post->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(\App\News\Domain\Event\NewsPostDeleted::class, $events[0]);
    }

    public function testGlobalAdminDeletesAnothersPost(): void
    {
        $author = User::create(Uuid::uuid4(), 'author@example.com', 'hash', 'Author');
        $admin = User::create(Uuid::uuid4(), 'admin@example.com', 'hash', 'Admin');
        $admin->promoteToGlobalAdmin();
        $post = NewsPost::create(Uuid::uuid4(), $author->id(), 'Hello');

        $postRepo = $this->createMock(NewsPostRepository::class);
        $postRepo->method('findById')->willReturn($post);
        $postRepo->expects(self::once())->method('remove')->with($post);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findById')->willReturn($admin);

        (new DeleteNewsPostCommandHandler($postRepo, new NewsGuard($userRepo)))
            ->__invoke(new DeleteNewsPostCommand((string) $post->id(), (string) $admin->id()));
    }

    public function testUnknownPostIsRejected(): void
    {
        $postRepo = $this->createMock(NewsPostRepository::class);
        $postRepo->method('findById')->willReturn(null);
        $postRepo->expects(self::never())->method('remove');

        $userRepo = $this->createMock(UserRepository::class);

        $this->expectException(NewsPostNotFoundException::class);
        (new DeleteNewsPostCommandHandler($postRepo, new NewsGuard($userRepo)))
            ->__invoke(new DeleteNewsPostCommand((string) Uuid::uuid4(), (string) Uuid::uuid4()));
    }

    public function testNewsMakerCannotDeleteAnothersPost(): void
    {
        $author = User::create(Uuid::uuid4(), 'author@example.com', 'hash', 'Author');
        $newsMaker = User::create(Uuid::uuid4(), 'news@example.com', 'hash', 'News');
        $newsMaker->promoteToNewsMaker();
        $post = NewsPost::create(Uuid::uuid4(), $author->id(), 'Hello');

        $postRepo = $this->createMock(NewsPostRepository::class);
        $postRepo->method('findById')->willReturn($post);
        $postRepo->expects(self::never())->method('remove');

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findById')->willReturn($newsMaker);

        $this->expectException(NewsPostForbiddenException::class);
        (new DeleteNewsPostCommandHandler($postRepo, new NewsGuard($userRepo)))
            ->__invoke(new DeleteNewsPostCommand((string) $post->id(), (string) $newsMaker->id()));
    }
}

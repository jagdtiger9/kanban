<?php

declare(strict_types=1);

namespace App\Tests\Unit\News\Application;

use App\News\Application\Message\Command\UpdateNewsPostCommand;
use App\News\Application\MessageHandler\UpdateNewsPostCommandHandler;
use App\News\Application\Service\NewsGuard;
use App\News\Domain\Entity\NewsPost;
use App\News\Domain\Exception\NewsPostForbiddenException;
use App\News\Domain\Exception\NewsPostNotFoundException;
use App\News\Domain\Repository\NewsPostRepository;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class UpdateNewsPostCommandHandlerTest extends TestCase
{
    public function testAuthorEditsPost(): void
    {
        $author = User::create(Uuid::uuid4(), 'author@example.com', 'hash', 'Author');
        $post = NewsPost::create(Uuid::uuid4(), $author->id(), 'Hello');
        $post->releaseEvents();

        $postRepo = $this->createMock(NewsPostRepository::class);
        $postRepo->method('findById')->willReturn($post);
        $postRepo->expects(self::once())->method('save')->with($post);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findById')->willReturnCallback(
            fn($id) => (string) $id === (string) $author->id() ? $author : null,
        );

        $result = (new UpdateNewsPostCommandHandler($postRepo, $userRepo, new NewsGuard($userRepo)))
            ->__invoke(new UpdateNewsPostCommand((string) $post->id(), 'Edited', (string) $author->id()));

        self::assertSame('Edited', $result['post']->body());
        self::assertSame($author, $result['author']);
    }

    public function testGlobalAdminEditsAnothersPost(): void
    {
        $author = User::create(Uuid::uuid4(), 'author@example.com', 'hash', 'Author');
        $admin = User::create(Uuid::uuid4(), 'admin@example.com', 'hash', 'Admin');
        $admin->promoteToGlobalAdmin();
        $post = NewsPost::create(Uuid::uuid4(), $author->id(), 'Hello');

        $postRepo = $this->createMock(NewsPostRepository::class);
        $postRepo->method('findById')->willReturn($post);
        $postRepo->expects(self::once())->method('save');

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findById')->willReturnCallback(
            fn($id) => (string) $id === (string) $admin->id() ? $admin : $author,
        );

        $result = (new UpdateNewsPostCommandHandler($postRepo, $userRepo, new NewsGuard($userRepo)))
            ->__invoke(new UpdateNewsPostCommand((string) $post->id(), 'Edited', (string) $admin->id()));

        self::assertSame('Edited', $result['post']->body());
        self::assertSame($author, $result['author']);
    }

    public function testUnknownPostIsRejected(): void
    {
        $postRepo = $this->createMock(NewsPostRepository::class);
        $postRepo->method('findById')->willReturn(null);
        $postRepo->expects(self::never())->method('save');

        $userRepo = $this->createMock(UserRepository::class);

        $this->expectException(NewsPostNotFoundException::class);
        (new UpdateNewsPostCommandHandler($postRepo, $userRepo, new NewsGuard($userRepo)))
            ->__invoke(new UpdateNewsPostCommand((string) Uuid::uuid4(), 'Edited', (string) Uuid::uuid4()));
    }

    public function testNewsMakerCannotEditAnothersPost(): void
    {
        $author = User::create(Uuid::uuid4(), 'author@example.com', 'hash', 'Author');
        $newsMaker = User::create(Uuid::uuid4(), 'news@example.com', 'hash', 'News');
        $newsMaker->promoteToNewsMaker();
        $post = NewsPost::create(Uuid::uuid4(), $author->id(), 'Hello');

        $postRepo = $this->createMock(NewsPostRepository::class);
        $postRepo->method('findById')->willReturn($post);
        $postRepo->expects(self::never())->method('save');

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findById')->willReturn($newsMaker);

        $this->expectException(NewsPostForbiddenException::class);
        (new UpdateNewsPostCommandHandler($postRepo, $userRepo, new NewsGuard($userRepo)))
            ->__invoke(new UpdateNewsPostCommand((string) $post->id(), 'Edited', (string) $newsMaker->id()));
    }
}

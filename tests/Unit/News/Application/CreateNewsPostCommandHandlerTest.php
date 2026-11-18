<?php

declare(strict_types=1);

namespace App\Tests\Unit\News\Application;

use App\News\Application\Message\Command\CreateNewsPostCommand;
use App\News\Application\MessageHandler\CreateNewsPostCommandHandler;
use App\News\Application\Service\NewsGuard;
use App\News\Domain\Entity\NewsPost;
use App\News\Domain\Repository\NewsPostRepository;
use App\Shared\Domain\Exception\AccessForbiddenException;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class CreateNewsPostCommandHandlerTest extends TestCase
{
    public function testNewsMakerCreatesPost(): void
    {
        $author = $this->newsMaker();
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findById')->willReturn($author);

        $postRepo = $this->createMock(NewsPostRepository::class);
        $postRepo->expects(self::once())
            ->method('save')
            ->with(self::callback(
                fn(NewsPost $post): bool => $post->authorId()->equals($author->id())
                    && $post->body() === 'Hello'
                    && $post->releaseEvents()[0] instanceof \App\News\Domain\Event\NewsPostCreated,
            ));

        $result = (new CreateNewsPostCommandHandler($postRepo, new NewsGuard($userRepo)))
            ->__invoke(new CreateNewsPostCommand('Hello', (string) $author->id()));

        self::assertSame($author, $result['author']);
        self::assertSame('Hello', $result['post']->body());
    }

    public function testGlobalAdminCreatesPost(): void
    {
        $author = $this->globalAdmin();
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findById')->willReturn($author);

        $postRepo = $this->createMock(NewsPostRepository::class);
        $postRepo->expects(self::once())->method('save');

        $result = (new CreateNewsPostCommandHandler($postRepo, new NewsGuard($userRepo)))
            ->__invoke(new CreateNewsPostCommand('Hello', (string) $author->id()));

        self::assertSame($author, $result['author']);
    }

    public function testPlainUserIsRejected(): void
    {
        $actor = User::create(Uuid::uuid4(), 'user@example.com', 'hash', 'User');
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findById')->willReturn($actor);

        $postRepo = $this->createMock(NewsPostRepository::class);
        $postRepo->expects(self::never())->method('save');

        $this->expectException(AccessForbiddenException::class);
        (new CreateNewsPostCommandHandler($postRepo, new NewsGuard($userRepo)))
            ->__invoke(new CreateNewsPostCommand('Hello', (string) $actor->id()));
    }

    private function newsMaker(): User
    {
        $user = User::create(Uuid::uuid4(), 'news@example.com', 'hash', 'News');
        $user->promoteToNewsMaker();

        return $user;
    }

    private function globalAdmin(): User
    {
        $user = User::create(Uuid::uuid4(), 'admin@example.com', 'hash', 'Admin');
        $user->promoteToGlobalAdmin();

        return $user;
    }
}

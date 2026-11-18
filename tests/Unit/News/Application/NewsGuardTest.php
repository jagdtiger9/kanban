<?php

declare(strict_types=1);

namespace App\Tests\Unit\News\Application;

use App\News\Application\Service\NewsGuard;
use App\News\Domain\Entity\NewsPost;
use App\News\Domain\Exception\NewsPostForbiddenException;
use App\Shared\Domain\Exception\AccessForbiddenException;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class NewsGuardTest extends TestCase
{
    private function guardWith(?User $actorUser): NewsGuard
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findById')->willReturn($actorUser);

        return new NewsGuard($userRepository);
    }

    private function user(string $email = 'user@example.com'): User
    {
        return User::create(Uuid::uuid4(), $email, 'hash', 'User');
    }

    public function testGlobalAdminCanPost(): void
    {
        $actor = $this->user('admin@example.com');
        $actor->promoteToGlobalAdmin();

        self::assertSame($actor, $this->guardWith($actor)->requirePostAccess($actor->id()));
    }

    public function testNewsMakerCanPost(): void
    {
        $actor = $this->user('news@example.com');
        $actor->promoteToNewsMaker();

        self::assertSame($actor, $this->guardWith($actor)->requirePostAccess($actor->id()));
    }

    public function testPlainUserCannotPost(): void
    {
        $this->expectException(AccessForbiddenException::class);

        $this->guardWith($this->user())->requirePostAccess(Uuid::uuid4());
    }

    public function testUnknownActorCannotPost(): void
    {
        $this->expectException(AccessForbiddenException::class);

        $this->guardWith(null)->requirePostAccess(Uuid::uuid4());
    }

    public function testAuthorCanModifyOwnPost(): void
    {
        $author = $this->user();
        $post = NewsPost::create(Uuid::uuid4(), $author->id(), 'Hello');

        $this->guardWith($author)->requireAuthorOrGlobalAdmin($post, $author->id());

        self::assertTrue(true);
    }

    public function testGlobalAdminCanModifyAnothersPost(): void
    {
        $author = $this->user();
        $admin = $this->user('admin@example.com');
        $admin->promoteToGlobalAdmin();
        $post = NewsPost::create(Uuid::uuid4(), $author->id(), 'Hello');

        $this->guardWith($admin)->requireAuthorOrGlobalAdmin($post, $admin->id());

        self::assertTrue(true);
    }

    public function testNewsMakerCannotModifyAnothersPost(): void
    {
        $author = $this->user();
        $newsMaker = $this->user('news@example.com');
        $newsMaker->promoteToNewsMaker();
        $post = NewsPost::create(Uuid::uuid4(), $author->id(), 'Hello');

        $this->expectException(NewsPostForbiddenException::class);
        $this->guardWith($newsMaker)->requireAuthorOrGlobalAdmin($post, $newsMaker->id());
    }

    public function testAnotherUserCannotModifyPost(): void
    {
        $author = $this->user();
        $other = $this->user('other@example.com');
        $post = NewsPost::create(Uuid::uuid4(), $author->id(), 'Hello');

        $this->expectException(NewsPostForbiddenException::class);
        $this->guardWith($other)->requireAuthorOrGlobalAdmin($post, $other->id());
    }
}

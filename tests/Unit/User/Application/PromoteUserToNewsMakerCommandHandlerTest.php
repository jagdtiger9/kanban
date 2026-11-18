<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Application;

use App\Shared\Domain\Exception\AccessForbiddenException;
use App\User\Application\Message\Command\PromoteUserToNewsMakerCommand;
use App\User\Application\MessageHandler\PromoteUserToNewsMakerCommandHandler;
use App\User\Application\Service\GlobalAdminGuard;
use App\User\Domain\Entity\User;
use App\User\Domain\Exception\UserNotFoundException;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class PromoteUserToNewsMakerCommandHandlerTest extends TestCase
{
    public function testPromotesAndSavesUser(): void
    {
        $actor = $this->globalAdmin();
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');

        $repo = $this->createMock(UserRepository::class);
        $repo->method('findById')->willReturnCallback(
            fn(UuidInterface $id): ?User => match ((string) $id) {
                (string) $actor->id() => $actor,
                (string) $user->id() => $user,
                default => null,
            },
        );
        $repo->expects(self::once())->method('save')->with($user);

        $result = (new PromoteUserToNewsMakerCommandHandler($repo, new GlobalAdminGuard($repo)))
            ->__invoke(new PromoteUserToNewsMakerCommand((string) $user->id(), (string) $actor->id()));

        self::assertSame($user, $result);
        self::assertTrue($user->isNewsMaker());
        self::assertSame(['ROLE_USER', 'ROLE_NEWS_MAKER'], $user->roles());
    }

    public function testDoesNotConfirmUnconfirmedUser(): void
    {
        $actor = $this->globalAdmin();
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');

        $repo = $this->createMock(UserRepository::class);
        $repo->method('findById')->willReturnCallback(
            fn(UuidInterface $id): ?User => match ((string) $id) {
                (string) $actor->id() => $actor,
                (string) $user->id() => $user,
                default => null,
            },
        );
        $repo->expects(self::once())->method('save')->with($user);

        (new PromoteUserToNewsMakerCommandHandler($repo, new GlobalAdminGuard($repo)))
            ->__invoke(new PromoteUserToNewsMakerCommand((string) $user->id(), (string) $actor->id()));

        self::assertFalse($user->isConfirmed());
    }

    public function testUnknownUserIsRejected(): void
    {
        $actor = $this->globalAdmin();

        $repo = $this->createMock(UserRepository::class);
        $repo->method('findById')->willReturnCallback(
            fn(UuidInterface $id): ?User => (string) $id === (string) $actor->id() ? $actor : null,
        );
        $repo->expects(self::never())->method('save');

        $this->expectException(UserNotFoundException::class);
        (new PromoteUserToNewsMakerCommandHandler($repo, new GlobalAdminGuard($repo)))
            ->__invoke(new PromoteUserToNewsMakerCommand((string) Uuid::uuid4(), (string) $actor->id()));
    }

    public function testNonAdminActorIsRejected(): void
    {
        $actor = User::create(Uuid::uuid4(), 'user@example.com', 'hash', 'User');
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');

        $repo = $this->createMock(UserRepository::class);
        $repo->method('findById')->willReturn($actor);
        $repo->expects(self::never())->method('save');

        $this->expectException(AccessForbiddenException::class);
        (new PromoteUserToNewsMakerCommandHandler($repo, new GlobalAdminGuard($repo)))
            ->__invoke(new PromoteUserToNewsMakerCommand((string) $user->id(), (string) $actor->id()));
    }

    private function globalAdmin(): User
    {
        $user = User::create(Uuid::uuid4(), 'admin@example.com', 'hash', 'Admin');
        $user->promoteToGlobalAdmin();

        return $user;
    }
}

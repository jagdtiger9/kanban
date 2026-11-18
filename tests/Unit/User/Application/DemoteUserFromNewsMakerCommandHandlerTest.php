<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Application;

use App\Shared\Domain\Exception\AccessForbiddenException;
use App\User\Application\Message\Command\DemoteUserFromNewsMakerCommand;
use App\User\Application\MessageHandler\DemoteUserFromNewsMakerCommandHandler;
use App\User\Application\Service\GlobalAdminGuard;
use App\User\Domain\Entity\User;
use App\User\Domain\Exception\UserNotFoundException;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class DemoteUserFromNewsMakerCommandHandlerTest extends TestCase
{
    public function testDemotesAndSavesUser(): void
    {
        $actor = $this->globalAdmin();
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $user->promoteToNewsMaker();

        $repo = $this->createMock(UserRepository::class);
        $repo->method('findById')->willReturnCallback(
            fn(UuidInterface $id): ?User => match ((string) $id) {
                (string) $actor->id() => $actor,
                (string) $user->id() => $user,
                default => null,
            },
        );
        $repo->expects(self::once())->method('save')->with($user);

        $result = (new DemoteUserFromNewsMakerCommandHandler($repo, new GlobalAdminGuard($repo)))
            ->__invoke(new DemoteUserFromNewsMakerCommand((string) $user->id(), (string) $actor->id()));

        self::assertSame($user, $result);
        self::assertFalse($user->isNewsMaker());
        self::assertSame(['ROLE_USER'], $user->roles());
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
        (new DemoteUserFromNewsMakerCommandHandler($repo, new GlobalAdminGuard($repo)))
            ->__invoke(new DemoteUserFromNewsMakerCommand((string) Uuid::uuid4(), (string) $actor->id()));
    }

    public function testNonAdminActorIsRejected(): void
    {
        $actor = User::create(Uuid::uuid4(), 'user@example.com', 'hash', 'User');
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $user->promoteToNewsMaker();

        $repo = $this->createMock(UserRepository::class);
        $repo->method('findById')->willReturn($actor);
        $repo->expects(self::never())->method('save');

        $this->expectException(AccessForbiddenException::class);
        (new DemoteUserFromNewsMakerCommandHandler($repo, new GlobalAdminGuard($repo)))
            ->__invoke(new DemoteUserFromNewsMakerCommand((string) $user->id(), (string) $actor->id()));
    }

    private function globalAdmin(): User
    {
        $user = User::create(Uuid::uuid4(), 'admin@example.com', 'hash', 'Admin');
        $user->promoteToGlobalAdmin();

        return $user;
    }
}

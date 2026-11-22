<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Application;

use App\User\Application\Message\Command\UpdateAvatarColorCommand;
use App\User\Application\MessageHandler\UpdateAvatarColorCommandHandler;
use App\User\Domain\Entity\User;
use App\User\Domain\Exception\UserNotFoundException;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class UpdateAvatarColorCommandHandlerTest extends TestCase
{
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
    }

    private function handler(): UpdateAvatarColorCommandHandler
    {
        return new UpdateAvatarColorCommandHandler($this->userRepository);
    }

    public function testStoresTheColorAndSavesTheUser(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $this->userRepository->method('findById')->willReturn($user);
        $this->userRepository->expects($this->once())->method('save')->with($user);

        $result = $this->handler()->__invoke(
            new UpdateAvatarColorCommand('#445566', (string)$user->id())
        );

        self::assertSame($user, $result);
        self::assertSame('#445566', $result->avatarColor());
    }

    public function testResetsTheColorWhenNullIsGiven(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $user->changeAvatarColor('#445566');
        $this->userRepository->method('findById')->willReturn($user);
        $this->userRepository->expects($this->once())->method('save')->with($user);

        $result = $this->handler()->__invoke(
            new UpdateAvatarColorCommand(null, (string)$user->id())
        );

        self::assertSame($user, $result);
        self::assertNull($result->avatarColor());
    }

    public function testThrowsWhenUserIsMissing(): void
    {
        $this->userRepository->method('findById')->willReturn(null);
        $this->userRepository->expects($this->never())->method('save');
        $this->expectException(UserNotFoundException::class);

        $this->handler()->__invoke(
            new UpdateAvatarColorCommand('#445566', (string)Uuid::uuid4())
        );
    }
}

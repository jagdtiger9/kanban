<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Domain\Entity;

use App\User\Domain\Entity\User;
use App\User\Domain\Event\UserAvatarChanged;
use App\User\Domain\Event\UserAvatarRemoved;
use App\User\Domain\Exception\ConfirmationTokenExpiredException;
use App\User\Domain\Exception\ConfirmationTokenInvalidException;
use App\User\Domain\Exception\UserAlreadyConfirmedException;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class UserTest extends TestCase
{
    public function testCreateNormalizesEmailAndDisplayName(): void
    {
        $user = User::create(Uuid::uuid4(), '  Alice@Example.COM ', 'hash', '  Alice  ');

        self::assertSame('alice@example.com', $user->email());
        self::assertSame('Alice', $user->displayName());
        self::assertFalse($user->isGlobalAdmin());
    }

    public function testCreateRejectsEmptyValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        User::create(Uuid::uuid4(), ' ', 'hash', 'Alice');
    }

    public function testChangeDisplayNameRejectsEmpty(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');

        $this->expectException(\InvalidArgumentException::class);
        $user->changeDisplayName('  ');
    }

    public function testGlobalAdminPromotionAndDemotion(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');

        $user->promoteToGlobalAdmin();
        self::assertTrue($user->isGlobalAdmin());

        $user->demoteFromGlobalAdmin();
        self::assertFalse($user->isGlobalAdmin());
    }

    public function testRolesIncludeGlobalAdminAfterPromotion(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');

        self::assertSame(['ROLE_USER'], $user->roles());

        $user->promoteToGlobalAdmin();
        self::assertSame(['ROLE_USER', 'ROLE_GLOBAL_ADMIN'], $user->roles());
    }

    public function testIdReturnsId(): void
    {
        $id = Uuid::uuid4();
        $user = User::create($id, 'alice@example.com', 'hash', 'Alice');

        self::assertTrue($user->id()->equals($id));
    }

    public function testNewUserIsNotConfirmed(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');

        self::assertFalse($user->isConfirmed());
        self::assertNull($user->confirmedAt());
        self::assertNull($user->confirmationTokenHash());
        self::assertNull($user->confirmationTokenExpiresAt());
    }

    public function testBeginEmailConfirmationStoresTokenAndExpiry(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $expiresAt = new \DateTimeImmutable('+24 hours');

        $user->beginEmailConfirmation(str_repeat('a', 64), $expiresAt);

        self::assertFalse($user->isConfirmed());
        self::assertSame(str_repeat('a', 64), $user->confirmationTokenHash());
        self::assertEquals($expiresAt, $user->confirmationTokenExpiresAt());
    }

    public function testBeginEmailConfirmationRejectsConfirmedUser(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $user->confirmAccount();

        $this->expectException(UserAlreadyConfirmedException::class);
        $user->beginEmailConfirmation(str_repeat('a', 64), new \DateTimeImmutable('+24 hours'));
    }

    public function testConfirmWithTokenConfirmsUserAndClearsToken(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $user->beginEmailConfirmation(str_repeat('a', 64), new \DateTimeImmutable('+24 hours'));
        $now = new \DateTimeImmutable();

        $user->confirmWithToken(str_repeat('a', 64), $now);

        self::assertTrue($user->isConfirmed());
        self::assertEquals($now, $user->confirmedAt());
        self::assertNull($user->confirmationTokenHash());
        self::assertNull($user->confirmationTokenExpiresAt());
    }

    public function testConfirmWithTokenRejectsInvalidToken(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $user->beginEmailConfirmation(str_repeat('a', 64), new \DateTimeImmutable('+24 hours'));

        $this->expectException(ConfirmationTokenInvalidException::class);
        $user->confirmWithToken(str_repeat('b', 64), new \DateTimeImmutable());
    }

    public function testConfirmWithTokenRejectsMissingToken(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');

        $this->expectException(ConfirmationTokenInvalidException::class);
        $user->confirmWithToken(str_repeat('a', 64), new \DateTimeImmutable());
    }

    public function testConfirmWithTokenRejectsExpiredToken(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $user->beginEmailConfirmation(str_repeat('a', 64), new \DateTimeImmutable('-1 second'));

        $this->expectException(ConfirmationTokenExpiredException::class);
        $user->confirmWithToken(str_repeat('a', 64), new \DateTimeImmutable());
    }

    public function testConfirmWithTokenRejectsAlreadyConfirmedUser(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $user->confirmAccount();

        $this->expectException(UserAlreadyConfirmedException::class);
        $user->confirmWithToken(str_repeat('a', 64), new \DateTimeImmutable());
    }

    public function testConfirmAccountConfirmsAndClearsPendingToken(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $user->beginEmailConfirmation(str_repeat('a', 64), new \DateTimeImmutable('+24 hours'));
        $before = new \DateTimeImmutable();

        $user->confirmAccount();

        self::assertTrue($user->isConfirmed());
        self::assertNotNull($user->confirmedAt());
        self::assertGreaterThanOrEqual($before, $user->confirmedAt());
        self::assertNull($user->confirmationTokenHash());
        self::assertNull($user->confirmationTokenExpiresAt());
    }

    public function testConfirmAccountRejectsAlreadyConfirmedUser(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $user->confirmAccount();

        $this->expectException(UserAlreadyConfirmedException::class);
        $user->confirmAccount();
    }

    public function testNewUserHasNoAvatar(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');

        self::assertNull($user->avatarStorageKey());
        self::assertNull($user->avatarUpdatedAt());
        self::assertNull($user->avatarVersion());
    }

    public function testChangeAvatarStoresKeyAndRecordsEvent(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');

        $user->changeAvatar('avatars/user-id/avatar.webp');

        self::assertSame('avatars/user-id/avatar.webp', $user->avatarStorageKey());
        self::assertNotNull($user->avatarUpdatedAt());
        self::assertNotNull($user->avatarVersion());

        $events = $user->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(UserAvatarChanged::class, $events[0]);
        self::assertSame($user->id()->toString(), $events[0]->userId);
        self::assertNull($events[0]->previousStorageKey);
        self::assertSame('avatars/user-id/avatar.webp', $events[0]->storageKey);
    }

    public function testChangeAvatarReportsPreviousKeyWhenReplacing(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $user->changeAvatar('avatars/one/old.webp');
        $previousVersion = $user->avatarVersion();
        $user->releaseEvents();
        usleep(2000);

        $user->changeAvatar('avatars/two/new.webp');

        $events = $user->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(UserAvatarChanged::class, $events[0]);
        self::assertSame('avatars/one/old.webp', $events[0]->previousStorageKey);
        self::assertSame('avatars/two/new.webp', $events[0]->storageKey);
        self::assertNotSame($previousVersion, $user->avatarVersion());
    }

    public function testChangeAvatarTrimsTheKey(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');

        $user->changeAvatar('  avatars/user-id/avatar.webp  ');

        self::assertSame('avatars/user-id/avatar.webp', $user->avatarStorageKey());
    }

    public function testChangeAvatarRejectsEmptyKey(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');

        $this->expectException(\InvalidArgumentException::class);
        $user->changeAvatar('   ');
    }

    public function testChangeAvatarRejectsOverlongKey(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');

        $this->expectException(\InvalidArgumentException::class);
        $user->changeAvatar(str_repeat('a', 256));
    }

    public function testRemoveAvatarClearsTheKeyAndRecordsEvent(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $user->changeAvatar('avatars/user-id/avatar.webp');
        $user->releaseEvents();

        $user->removeAvatar();

        self::assertNull($user->avatarStorageKey());
        self::assertNull($user->avatarUpdatedAt());
        self::assertNull($user->avatarVersion());

        $events = $user->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(UserAvatarRemoved::class, $events[0]);
        self::assertSame($user->id()->toString(), $events[0]->userId);
        self::assertSame('avatars/user-id/avatar.webp', $events[0]->storageKey);
    }

    public function testRemoveAvatarWithoutAvatarIsANoOp(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');

        $user->removeAvatar();

        self::assertNull($user->avatarStorageKey());
        self::assertSame([], $user->releaseEvents());
    }
}

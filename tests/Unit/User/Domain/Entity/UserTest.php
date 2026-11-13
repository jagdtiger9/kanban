<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Domain\Entity;

use App\User\Domain\Entity\User;
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
}

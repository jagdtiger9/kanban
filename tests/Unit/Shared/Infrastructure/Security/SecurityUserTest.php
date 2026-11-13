<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Security;

use App\Shared\Infrastructure\Security\SecurityUser;
use App\User\Domain\Entity\User;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Security\Core\User\UserInterface;

final class SecurityUserTest extends TestCase
{
    public function testWrapsDomainUser(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');

        $securityUser = SecurityUser::fromUser($user);

        self::assertSame($user, $securityUser->user());
        self::assertTrue($securityUser->getId()->equals($user->id()));
        self::assertSame('alice@example.com', $securityUser->getUserIdentifier());
        self::assertSame('hash', $securityUser->getPassword());
    }

    public function testRolesMirrorDomainState(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $securityUser = SecurityUser::fromUser($user);

        self::assertSame(['ROLE_USER'], $securityUser->getRoles());
        self::assertSame('["ROLE_USER"]', $securityUser->getRolesString());

        $user->promoteToGlobalAdmin();
        self::assertSame(['ROLE_USER', 'ROLE_GLOBAL_ADMIN'], $securityUser->getRoles());
    }

    public function testEraseCredentialsKeepsPassword(): void
    {
        $securityUser = SecurityUser::fromUser(
            User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice'),
        );

        // the password participates in the remember-me signature
        $securityUser->eraseCredentials();

        self::assertSame('hash', $securityUser->getPassword());
    }

    public function testIsEqualToComparesIdsOnly(): void
    {
        $id = Uuid::uuid4();
        $user = SecurityUser::fromUser(User::create($id, 'alice@example.com', 'hash', 'Alice'));
        $sameIdOtherState = SecurityUser::fromUser(User::create($id, 'alice@example.com', 'other-hash', 'Alice'));
        $otherId = SecurityUser::fromUser(User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice'));
        $foreignUser = new class implements UserInterface {
            public function getRoles(): array
            {
                return ['ROLE_USER'];
            }

            public function eraseCredentials(): void
            {
            }

            public function getUserIdentifier(): string
            {
                return 'alice@example.com';
            }
        };

        self::assertTrue($user->isEqualTo($sameIdOtherState));
        self::assertFalse($user->isEqualTo($otherId));
        self::assertFalse($user->isEqualTo($foreignUser));
    }
}

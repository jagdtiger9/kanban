<?php

declare(strict_types=1);

namespace App\Tests\Integration\User\Infrastructure;

use App\Tests\Integration\DatabaseTestCase;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use Ramsey\Uuid\Uuid;

final class DoctrineUserRepositoryTest extends DatabaseTestCase
{
    private UserRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = $this->repo(UserRepository::class);
    }

    public function testSaveAndFindById(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $this->repo->save($user);
        $this->em()->clear();

        $found = $this->repo->findById($user->id());

        self::assertNotNull($found);
        self::assertSame('Alice', $found->displayName());
    }

    public function testEmailIsStoredNormalizedAndFindable(): void
    {
        $user = User::create(Uuid::uuid4(), '  Bob@Example.COM ', 'hash', 'Bob');
        $this->repo->save($user);
        $this->em()->clear();

        $found = $this->repo->findByEmail('bob@example.com');

        self::assertNotNull($found);
        self::assertTrue($found->id()->equals($user->id()));
    }

    public function testFindMissingReturnsNull(): void
    {
        self::assertNull($this->repo->findById(Uuid::uuid4()));
        self::assertNull($this->repo->findByEmail('nobody@example.com'));
    }

    public function testFindBySearchTermMatchesEmailOrDisplayName(): void
    {
        $alice = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice Cooper');
        $bob = User::create(Uuid::uuid4(), 'bob@example.com', 'hash', 'Bob Marley');
        $carol = User::create(Uuid::uuid4(), 'carol@other.org', 'hash', 'Carol King');
        $this->repo->save($alice);
        $this->repo->save($bob);
        $this->repo->save($carol);
        $this->em()->clear();

        $byEmail = $this->repo->findBySearchTerm('ALICE');
        self::assertCount(1, $byEmail);
        self::assertTrue($byEmail[0]->id()->equals($alice->id()));

        $byName = $this->repo->findBySearchTerm('marley');
        self::assertCount(1, $byName);
        self::assertTrue($byName[0]->id()->equals($bob->id()));

        $partial = $this->repo->findBySearchTerm('car');
        self::assertCount(1, $partial);
        self::assertTrue($partial[0]->id()->equals($carol->id()));

        self::assertSame([], $this->repo->findBySearchTerm('zzz'));
        self::assertSame([], $this->repo->findBySearchTerm('  '));
    }

    public function testFindByConfirmationTokenHash(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $user->beginEmailConfirmation(hash('sha256', 'secret-token'), new \DateTimeImmutable('+24 hours'));
        $this->repo->save($user);
        $this->em()->clear();

        $found = $this->repo->findByConfirmationTokenHash(hash('sha256', 'secret-token'));

        self::assertNotNull($found);
        self::assertTrue($found->id()->equals($user->id()));
        self::assertNull($this->repo->findByConfirmationTokenHash(hash('sha256', 'other')));
    }

    public function testFindAllOrderedByDisplayName(): void
    {
        $this->repo->save(User::create(Uuid::uuid4(), 'zed@example.com', 'hash', 'Zed'));
        $this->repo->save(User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice'));
        $this->repo->save(User::create(Uuid::uuid4(), 'bob@example.com', 'hash', 'Bob'));
        $this->em()->clear();

        $all = $this->repo->findAllOrderedByDisplayName();

        self::assertSame(['Alice', 'Bob', 'Zed'], array_map(static fn (User $u): string => $u->displayName(), $all));
    }
}

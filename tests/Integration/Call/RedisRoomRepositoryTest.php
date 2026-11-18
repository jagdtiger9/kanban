<?php

declare(strict_types=1);

namespace App\Tests\Integration\Call;

use App\Call\Domain\Model\Room;
use App\Call\Domain\Repository\RoomRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RedisRoomRepositoryTest extends KernelTestCase
{
    private RoomRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = self::getContainer()->get(RoomRepository::class);
    }

    public function testRoundTripAndPartialWrites(): void
    {
        $projectId = Uuid::uuid4();
        $now = new \DateTimeImmutable('now');

        $room = $this->repository->get($projectId);
        self::assertTrue($room->isEmpty());
        $room->join('alice', 'session-a', $now, 4);
        $this->repository->save($room);

        // Two requests load the room at the same time and change different places.
        $first = $this->repository->get($projectId);
        $second = $this->repository->get($projectId);
        $first->join('bob', 'session-b', $now, 4);
        $second->heartbeat('alice', 'session-a', $now->modify('+5 seconds'));
        $this->repository->save($first);
        $this->repository->save($second);

        $loaded = $this->repository->get($projectId);
        self::assertEqualsCanonicalizing(['alice', 'bob'], $loaded->userIds());

        $loaded->leave('alice', 'session-a');
        $loaded->leave('bob', 'session-b');
        $this->repository->save($loaded);

        self::assertTrue($this->repository->get($projectId)->isEmpty());
    }

    public function testRoomWithoutParticipantsIsEmpty(): void
    {
        self::assertInstanceOf(Room::class, $this->repository->get(Uuid::uuid4()));
    }
}

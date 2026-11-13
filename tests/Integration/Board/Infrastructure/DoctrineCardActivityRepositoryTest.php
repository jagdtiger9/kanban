<?php

declare(strict_types=1);

namespace App\Tests\Integration\Board\Infrastructure;

use App\Board\Domain\Entity\CardActivity;
use App\Board\Domain\Repository\CardActivityRepository;
use App\Tests\Integration\DatabaseTestCase;
use Ramsey\Uuid\Uuid;

final class DoctrineCardActivityRepositoryTest extends DatabaseTestCase
{
    private CardActivityRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = $this->repo(CardActivityRepository::class);
    }

    public function testSaveAndFindByCardWithJsonPayloadRoundtrip(): void
    {
        $cardId = Uuid::uuid4();
        $activity = CardActivity::create(
            Uuid::uuid4(),
            $cardId,
            Uuid::uuid4(),
            'card.moved',
            ['old_column' => 'todo', 'new_column' => 'done', 'position' => 3],
        );
        $this->repo->save($activity);
        $this->em()->clear();

        $activities = $this->repo->findByCard($cardId);

        self::assertCount(1, $activities);
        self::assertSame('card.moved', $activities[0]->type());
        self::assertSame(
            ['old_column' => 'todo', 'new_column' => 'done', 'position' => 3],
            $activities[0]->payload(),
        );
        self::assertTrue($activities[0]->actorId()->equals($activity->actorId()));
    }

    public function testFindByCardReturnsEmptyForUnknownCard(): void
    {
        self::assertSame([], $this->repo->findByCard(Uuid::uuid4()));
    }
}

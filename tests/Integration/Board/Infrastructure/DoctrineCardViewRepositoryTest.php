<?php

declare(strict_types=1);

namespace App\Tests\Integration\Board\Infrastructure;

use App\Board\Domain\Entity\CardView;
use App\Board\Domain\Repository\CardViewRepository;
use App\Tests\Integration\DatabaseTestCase;
use Ramsey\Uuid\Uuid;

final class DoctrineCardViewRepositoryTest extends DatabaseTestCase
{
    private CardViewRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = $this->repo(CardViewRepository::class);
    }

    public function testSaveAndFindByUserAndCardRoundtrip(): void
    {
        $userId = Uuid::uuid4();
        $cardId = Uuid::uuid4();
        $view = CardView::create(Uuid::uuid4(), $userId, $cardId);
        $this->repo->save($view);
        $this->em()->clear();

        $found = $this->repo->findByUserAndCard($userId, $cardId);

        self::assertInstanceOf(CardView::class, $found);
        self::assertTrue($found->id()->equals($view->id()));
        self::assertSame(
            $view->lastSeenAt()->format('U.u'),
            $found->lastSeenAt()->format('U.u'),
        );
    }

    public function testMarkSeenPersistsAdvancedTimestamp(): void
    {
        $userId = Uuid::uuid4();
        $cardId = Uuid::uuid4();
        $this->repo->save(CardView::create(Uuid::uuid4(), $userId, $cardId));

        $view = $this->repo->findByUserAndCard($userId, $cardId);
        self::assertInstanceOf(CardView::class, $view);
        $seenAt = (new \DateTimeImmutable())->modify('+1 hour');
        $view->markSeen($seenAt);
        $this->repo->save($view);
        $this->em()->clear();

        $found = $this->repo->findByUserAndCard($userId, $cardId);

        self::assertInstanceOf(CardView::class, $found);
        self::assertSame($seenAt->format('U.u'), $found->lastSeenAt()->format('U.u'));
    }

    public function testFindByUserAndCardReturnsNullForUnknownPair(): void
    {
        self::assertNull($this->repo->findByUserAndCard(Uuid::uuid4(), Uuid::uuid4()));
    }
}

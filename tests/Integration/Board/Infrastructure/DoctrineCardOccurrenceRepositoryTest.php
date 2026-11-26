<?php

declare(strict_types=1);

namespace App\Tests\Integration\Board\Infrastructure;

use App\Board\Domain\Entity\CardOccurrence;
use App\Board\Domain\Repository\CardOccurrenceRepository;
use App\Tests\Integration\DatabaseTestCase;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Ramsey\Uuid\Uuid;

final class DoctrineCardOccurrenceRepositoryTest extends DatabaseTestCase
{
    private CardOccurrenceRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = $this->repo(CardOccurrenceRepository::class);
    }

    public function testSaveAndFindByCardAndPeriodRoundtrip(): void
    {
        $cardId = Uuid::uuid4();
        $userId = Uuid::uuid4();
        $occurrence = CardOccurrence::create(
            Uuid::uuid4(),
            $cardId,
            new \DateTimeImmutable('2026-11-26'),
            $userId,
        );
        $this->repo->save($occurrence);
        $this->em()->clear();

        $found = $this->repo->findByCardAndPeriod($cardId, new \DateTimeImmutable('2026-11-26'));

        self::assertInstanceOf(CardOccurrence::class, $found);
        self::assertTrue($found->id()->equals($occurrence->id()));
        self::assertSame('2026-11-26', $found->period()->format('Y-m-d'));
        self::assertSame($userId->toString(), $found->completedBy()?->toString());
        self::assertSame(
            $occurrence->completedAt()->format('U.u'),
            $found->completedAt()->format('U.u'),
        );
    }

    public function testFindByCardAndPeriodReturnsNullForUnknownPeriod(): void
    {
        $cardId = Uuid::uuid4();
        $this->repo->save(CardOccurrence::create(Uuid::uuid4(), $cardId, new \DateTimeImmutable('2026-11-26'), Uuid::uuid4()));
        $this->em()->clear();

        self::assertNull($this->repo->findByCardAndPeriod($cardId, new \DateTimeImmutable('2026-11-27')));
        self::assertNull($this->repo->findByCardAndPeriod(Uuid::uuid4(), new \DateTimeImmutable('2026-11-26')));
    }

    public function testFindCompletedPeriodsByCardsGroupsAndOrdersPeriods(): void
    {
        $cardA = Uuid::uuid4();
        $cardB = Uuid::uuid4();
        foreach (
            [
                [$cardA, '2026-11-20'],
                [$cardA, '2026-11-18'],
                [$cardA, '2026-11-25'],
                [$cardB, '2026-11-19'],
            ] as [$cardId, $period]
        ) {
            $this->repo->save(CardOccurrence::create(Uuid::uuid4(), $cardId, new \DateTimeImmutable($period), Uuid::uuid4()));
        }
        $this->em()->clear();

        $periods = $this->repo->findCompletedPeriodsByCards([$cardA, $cardB, Uuid::uuid4()]);

        self::assertSame(
            [
                $cardA->toString() => ['2026-11-18', '2026-11-20', '2026-11-25'],
                $cardB->toString() => ['2026-11-19'],
            ],
            $periods,
        );
    }

    public function testFindCompletedPeriodsByCardsReturnsEmptyMapForNoIds(): void
    {
        self::assertSame([], $this->repo->findCompletedPeriodsByCards([]));
    }

    public function testDuplicateCardPeriodIsRejected(): void
    {
        $cardId = Uuid::uuid4();
        $this->repo->save(CardOccurrence::create(Uuid::uuid4(), $cardId, new \DateTimeImmutable('2026-11-26'), Uuid::uuid4()));

        $this->expectException(UniqueConstraintViolationException::class);

        $this->repo->save(CardOccurrence::create(Uuid::uuid4(), $cardId, new \DateTimeImmutable('2026-11-26'), Uuid::uuid4()));
    }

    public function testRemoveDeletesTheOccurrence(): void
    {
        $cardId = Uuid::uuid4();
        $occurrence = CardOccurrence::create(Uuid::uuid4(), $cardId, new \DateTimeImmutable('2026-11-26'), Uuid::uuid4());
        $this->repo->save($occurrence);

        $this->repo->remove($occurrence);
        $this->em()->clear();

        self::assertNull($this->repo->findByCardAndPeriod($cardId, new \DateTimeImmutable('2026-11-26')));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Domain\Service;

use App\Board\Domain\Enum\OccurrenceState;
use App\Board\Domain\Service\OccurrenceCalculator;
use App\Board\Domain\ValueObject\RecurrenceRule;
use PHPUnit\Framework\TestCase;

final class OccurrenceCalculatorTest extends TestCase
{
    private OccurrenceCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new OccurrenceCalculator();
    }

    public function testEveryDayCurrentPeriodIsToday(): void
    {
        self::assertSame(
            '2026-11-26',
            $this->calculator->currentPeriod(
                RecurrenceRule::weekly([1, 2, 3, 4, 5, 6, 7]),
                new \DateTimeImmutable('2026-11-26 15:30'),
            ),
        );
    }

    public function testEveryDayIsCompletedWhenTodayIsCompleted(): void
    {
        self::assertSame(
            OccurrenceState::Completed,
            $this->calculator->state(
                RecurrenceRule::weekly([1, 2, 3, 4, 5, 6, 7]),
                ['2026-11-26'],
                new \DateTimeImmutable('2026-11-26'),
            ),
        );
    }

    public function testEveryDayIsPendingOtherwise(): void
    {
        $rule = RecurrenceRule::weekly([1, 2, 3, 4, 5, 6, 7]);

        self::assertSame(
            OccurrenceState::Pending,
            $this->calculator->state($rule, [], new \DateTimeImmutable('2026-11-26')),
        );
        self::assertSame(
            OccurrenceState::Pending,
            $this->calculator->state($rule, ['2026-11-25'], new \DateTimeImmutable('2026-11-26')),
        );
    }

    public function testWeeklyCurrentPeriodIsMostRecentMatchingWeekday(): void
    {
        // 2026-11-26 is a Thursday
        self::assertSame(
            '2026-11-23',
            $this->calculator->currentPeriod(RecurrenceRule::weekly([1]), new \DateTimeImmutable('2026-11-26')),
        );
        self::assertSame(
            '2026-11-20',
            $this->calculator->currentPeriod(RecurrenceRule::weekly([5]), new \DateTimeImmutable('2026-11-26')),
        );
    }

    public function testWeeklyIncludesTodayWhenTodayMatches(): void
    {
        self::assertSame(
            '2026-11-26',
            $this->calculator->currentPeriod(RecurrenceRule::weekly([1, 4]), new \DateTimeImmutable('2026-11-26')),
        );
    }

    public function testWeeklyIsPendingWhenTheCurrentPeriodIsUncompleted(): void
    {
        self::assertSame(
            OccurrenceState::Pending,
            $this->calculator->state(RecurrenceRule::weekly([4]), [], new \DateTimeImmutable('2026-11-26')),
        );
        // The period fell on Monday and today is Thursday — still pending, not overdue.
        self::assertSame(
            OccurrenceState::Pending,
            $this->calculator->state(RecurrenceRule::weekly([1]), [], new \DateTimeImmutable('2026-11-26')),
        );
    }

    public function testWeeklyIsCompletedWhenCurrentPeriodIsCompleted(): void
    {
        self::assertSame(
            OccurrenceState::Completed,
            $this->calculator->state(
                RecurrenceRule::weekly([1]),
                ['2026-11-23'],
                new \DateTimeImmutable('2026-11-26'),
            ),
        );
    }

    public function testWeeklyResetsAfterTheNextOccurrenceArrives(): void
    {
        self::assertSame(
            OccurrenceState::Pending,
            $this->calculator->state(
                RecurrenceRule::weekly([4]),
                ['2026-11-19'],
                new \DateTimeImmutable('2026-11-26'),
            ),
        );
    }

    public function testMonthlyCurrentPeriodPicksLatestMatchingDayNotAfterToday(): void
    {
        self::assertSame(
            '2026-11-15',
            $this->calculator->currentPeriod(RecurrenceRule::monthly([1, 15, 20]), new \DateTimeImmutable('2026-11-18')),
        );
    }

    public function testMonthlyIncludesTodayWhenTodayMatches(): void
    {
        self::assertSame(
            '2026-11-26',
            $this->calculator->currentPeriod(RecurrenceRule::monthly([26]), new \DateTimeImmutable('2026-11-26')),
        );
    }

    public function testMonthlyIsPendingWhenCurrentPeriodIsUncompleted(): void
    {
        self::assertSame(
            OccurrenceState::Pending,
            $this->calculator->state(RecurrenceRule::monthly([25]), [], new \DateTimeImmutable('2026-11-26')),
        );
    }

    public function testMonthlyIsCompletedWhenCurrentPeriodIsCompleted(): void
    {
        self::assertSame(
            OccurrenceState::Completed,
            $this->calculator->state(
                RecurrenceRule::monthly([25]),
                ['2026-11-25'],
                new \DateTimeImmutable('2026-11-26'),
            ),
        );
    }

    public function testMonthlyClampsDay31ToTheLastDayOfTheMonth(): void
    {
        self::assertSame(
            '2026-04-30',
            $this->calculator->currentPeriod(RecurrenceRule::monthly([31]), new \DateTimeImmutable('2026-04-30')),
        );
        self::assertSame(
            '2028-02-29',
            $this->calculator->currentPeriod(RecurrenceRule::monthly([31]), new \DateTimeImmutable('2028-02-29')),
        );
        self::assertSame(
            '2026-01-31',
            $this->calculator->currentPeriod(RecurrenceRule::monthly([31]), new \DateTimeImmutable('2026-02-15')),
        );
    }

    public function testTimeOfDayDoesNotAffectThePeriod(): void
    {
        self::assertSame(
            '2026-11-26',
            $this->calculator->currentPeriod(
                RecurrenceRule::weekly([1, 2, 3, 4, 5, 6, 7]),
                new \DateTimeImmutable('2026-11-26 23:59:59'),
            ),
        );
        self::assertSame(
            '2026-11-23',
            $this->calculator->currentPeriod(RecurrenceRule::weekly([1]), new \DateTimeImmutable('2026-11-26 00:00:01')),
        );
    }
}

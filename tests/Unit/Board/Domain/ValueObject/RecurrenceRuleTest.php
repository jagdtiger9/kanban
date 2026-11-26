<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Domain\ValueObject;

use App\Board\Domain\Enum\RecurrenceKind;
use App\Board\Domain\ValueObject\RecurrenceRule;
use PHPUnit\Framework\TestCase;

final class RecurrenceRuleTest extends TestCase
{
    public function testWeeklyNormalizesWeekdays(): void
    {
        $rule = RecurrenceRule::weekly([5, 1, 3, 3]);

        self::assertSame(RecurrenceKind::Weekly, $rule->kind());
        self::assertSame([1, 3, 5], $rule->weekdays());
    }

    public function testWeeklyAcceptsEveryWeekday(): void
    {
        $rule = RecurrenceRule::weekly([7, 1, 2, 3, 4, 5, 6]);

        self::assertSame([1, 2, 3, 4, 5, 6, 7], $rule->weekdays());
    }

    public function testWeeklyRejectsEmptyWeekdays(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RecurrenceRule::weekly([]);
    }

    public function testWeeklyRejectsOutOfRangeWeekday(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RecurrenceRule::weekly([0, 8]);
    }

    public function testWeeklyRejectsNonIntegerWeekday(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RecurrenceRule::weekly(['2026-12-25']);
    }

    public function testMonthlyNormalizesMonthDays(): void
    {
        $rule = RecurrenceRule::monthly([31, 1, 15, 1]);

        self::assertSame(RecurrenceKind::Monthly, $rule->kind());
        self::assertSame([1, 15, 31], $rule->monthDays());
    }

    public function testMonthlyRejectsEmptyMonthDays(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RecurrenceRule::monthly([]);
    }

    public function testMonthlyRejectsOutOfRangeMonthDay(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RecurrenceRule::monthly([32]);
    }

    public function testFromArrayRoundTripsEveryKind(): void
    {
        $rules = [
            RecurrenceRule::weekly([1, 2, 3, 4, 5, 6, 7]),
            RecurrenceRule::weekly([1, 3, 5]),
            RecurrenceRule::monthly([1, 15, 31]),
        ];

        foreach ($rules as $rule) {
            self::assertTrue($rule->equals(RecurrenceRule::fromArray($rule->toArray())));
        }
    }

    public function testToArrayAlwaysHasTheSameShape(): void
    {
        self::assertSame(
            ['kind' => 'weekly', 'weekdays' => [1], 'monthDays' => []],
            RecurrenceRule::weekly([1])->toArray(),
        );
    }

    public function testFromArrayRejectsUnknownKind(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RecurrenceRule::fromArray(['kind' => 'hourly']);
    }

    public function testFromArrayRejectsRemovedDailyKind(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RecurrenceRule::fromArray(['kind' => 'daily']);
    }

    public function testFromArrayRejectsMissingWeekdays(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RecurrenceRule::fromArray(['kind' => 'weekly']);
    }

    public function testFromArrayRejectsRemovedOnceKind(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RecurrenceRule::fromArray(['kind' => 'once', 'targetDate' => '2026-12-31']);
    }

    public function testFromArrayRejectsNonArrayList(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RecurrenceRule::fromArray(['kind' => 'weekly', 'weekdays' => 5]);
    }

    public function testEqualsComparesKindAndFields(): void
    {
        self::assertTrue(RecurrenceRule::weekly([1, 3])->equals(RecurrenceRule::weekly([3, 1, 1])));
        self::assertFalse(RecurrenceRule::weekly([1, 3])->equals(RecurrenceRule::weekly([1, 4])));
        self::assertFalse(RecurrenceRule::weekly([1])->equals(RecurrenceRule::monthly([1])));
        self::assertFalse(RecurrenceRule::monthly([1])->equals(RecurrenceRule::monthly([2])));
    }
}

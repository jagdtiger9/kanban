<?php

declare(strict_types=1);

namespace App\Board\Domain\Service;

use App\Board\Domain\Enum\OccurrenceState;
use App\Board\Domain\Enum\RecurrenceKind;
use App\Board\Domain\ValueObject\RecurrenceRule;

final class OccurrenceCalculator
{
    public function currentPeriod(RecurrenceRule $rule, \DateTimeImmutable $today): string
    {
        return match ($rule->kind()) {
            RecurrenceKind::Weekly => $this->weeklyPeriod($rule, $today),
            RecurrenceKind::Monthly => $this->monthlyPeriod($rule, $today),
        };
    }

    /** @param list<string> $completedPeriods */
    public function state(RecurrenceRule $rule, array $completedPeriods, \DateTimeImmutable $today): OccurrenceState
    {
        return in_array($this->currentPeriod($rule, $today), $completedPeriods, true)
            ? OccurrenceState::Completed
            : OccurrenceState::Pending;
    }

    private function weeklyPeriod(RecurrenceRule $rule, \DateTimeImmutable $today): string
    {
        $todayWeekday = (int)$today->format('N');
        $offset = 7;
        foreach ($rule->weekdays() as $weekday) {
            $offset = min($offset, ($todayWeekday - $weekday + 7) % 7);
        }

        return $today->modify(sprintf('-%d days', $offset))->format('Y-m-d');
    }

    private function monthlyPeriod(RecurrenceRule $rule, \DateTimeImmutable $today): string
    {
        $dayOfMonth = (int)$today->format('j');
        $daysInMonth = (int)$today->format('t');
        $latest = null;
        foreach ($rule->monthDays() as $monthDay) {
            $effective = min($monthDay, $daysInMonth);
            if ($effective <= $dayOfMonth && ($latest === null || $effective > $latest)) {
                $latest = $effective;
            }
        }
        if ($latest !== null) {
            return $today->setDate((int)$today->format('Y'), (int)$today->format('n'), $latest)->format('Y-m-d');
        }

        $previousMonth = $today->modify('first day of last month');
        $daysInPreviousMonth = (int)$previousMonth->format('t');
        $latest = 0;
        foreach ($rule->monthDays() as $monthDay) {
            $latest = max($latest, min($monthDay, $daysInPreviousMonth));
        }

        return $previousMonth
            ->setDate((int)$previousMonth->format('Y'), (int)$previousMonth->format('n'), $latest)
            ->format('Y-m-d');
    }
}

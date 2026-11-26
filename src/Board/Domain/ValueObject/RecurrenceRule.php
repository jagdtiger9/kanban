<?php

declare(strict_types=1);

namespace App\Board\Domain\ValueObject;

use App\Board\Domain\Enum\RecurrenceKind;

final class RecurrenceRule
{
    /**
     * @param list<int> $weekdays
     * @param list<int> $monthDays
     */
    private function __construct(
        private readonly RecurrenceKind $kind,
        private readonly array $weekdays,
        private readonly array $monthDays,
    ) {
    }

    /** @param array<mixed> $weekdays */
    public static function weekly(array $weekdays): self
    {
        return new self(RecurrenceKind::Weekly, self::normalizeInts($weekdays, 1, 7, 'weekday'), []);
    }

    /** @param array<mixed> $monthDays */
    public static function monthly(array $monthDays): self
    {
        return new self(RecurrenceKind::Monthly, [], self::normalizeInts($monthDays, 1, 31, 'month day'));
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $kind = $data['kind'] ?? null;
        if (!is_string($kind) || RecurrenceKind::tryFrom($kind) === null) {
            throw new \InvalidArgumentException('Recurrence kind must be one of: weekly, monthly');
        }

        return match (RecurrenceKind::from($kind)) {
            RecurrenceKind::Weekly => self::weekly(self::requireList($data['weekdays'] ?? [], 'weekdays')),
            RecurrenceKind::Monthly => self::monthly(self::requireList($data['monthDays'] ?? [], 'month days')),
        };
    }

    /** @return array{kind: string, weekdays: list<int>, monthDays: list<int>} */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind->value,
            'weekdays' => $this->weekdays,
            'monthDays' => $this->monthDays,
        ];
    }

    public function equals(self $other): bool
    {
        return $this->toArray() === $other->toArray();
    }

    public function kind(): RecurrenceKind
    {
        return $this->kind;
    }

    /** @return list<int> */
    public function weekdays(): array
    {
        return $this->weekdays;
    }

    /** @return list<int> */
    public function monthDays(): array
    {
        return $this->monthDays;
    }

    /**
     * @param array<mixed> $values
     *
     * @return list<int>
     */
    private static function normalizeInts(array $values, int $min, int $max, string $label): array
    {
        if ($values === []) {
            throw new \InvalidArgumentException(sprintf('Recurrence requires at least one %s', $label));
        }

        $unique = [];
        foreach ($values as $value) {
            if (!is_int($value) || $value < $min || $value > $max) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Every %s must be an integer between %d and %d',
                        $label,
                        $min,
                        $max,
                    )
                );
            }
            $unique[$value] = true;
        }

        $result = array_keys($unique);
        sort($result);

        return $result;
    }

    /** @return array<mixed> */
    private static function requireList(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException(sprintf('The %s list must be an array', $label));
        }

        return $value;
    }
}

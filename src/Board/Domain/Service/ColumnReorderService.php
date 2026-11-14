<?php

declare(strict_types=1);

namespace App\Board\Domain\Service;

use App\Board\Domain\Entity\Column;
use Ramsey\Uuid\UuidInterface;

final class ColumnReorderService
{
    /**
     * Moves a column to a new index within its board and renumbers all
     * positions into a dense 0..n sequence.
     *
     * @param list<Column> $columns all columns of the board, ordered by position
     */
    public function reorder(Column $column, int $targetIndex, array $columns, ?UuidInterface $actorId = null): void
    {
        if ($targetIndex < 0) {
            throw new \InvalidArgumentException('Target index must not be negative');
        }

        $rest = array_values(array_filter(
            $columns,
            static fn (Column $c): bool => !$c->id()->equals($column->id()),
        ));
        $index = min($targetIndex, count($rest));
        array_splice($rest, $index, 0, [$column]);

        $this->renumber($rest);
        $column->recordReordered($actorId);
    }

    /**
     * Renumbers columns into a dense 0..n sequence, keeping their relative order.
     *
     * @param list<Column> $columns
     */
    public function renumber(array $columns): void
    {
        foreach ($columns as $i => $column) {
            $column->changePosition($i);
        }
    }
}

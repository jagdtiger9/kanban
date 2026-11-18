<?php

declare(strict_types=1);

namespace App\Shared\Application\Media;

use App\Shared\Application\Storage\FileStorage;

final class MediaSweeper
{
    public function __construct(private FileStorage $storage)
    {
    }

    /**
     * @param list<string> $referencedKeys
     * @param list<string> $managedPrefixes
     */
    public function sweep(
        array $referencedKeys,
        array $managedPrefixes,
        \DateTimeImmutable $now,
        \DateInterval $gracePeriod,
        bool $dryRun,
    ): MediaSweepReport {
        $stored = $this->storage->keys();
        $referenced = array_values(array_unique($referencedKeys));
        sort($referenced);

        $storedLookup = array_fill_keys($stored, true);
        $referencedLookup = array_fill_keys($referenced, true);
        $cutoff = $now->sub($gracePeriod);

        $unreferenced = [];
        $retained = [];
        $deleted = [];

        foreach ($stored as $key) {
            if (isset($referencedLookup[$key]) || !$this->isManaged($key, $managedPrefixes)) {
                continue;
            }

            $unreferenced[] = $key;
            $modifiedAt = $this->storage->lastModified($key);

            if ($modifiedAt === null || $modifiedAt >= $cutoff) {
                $retained[] = $key;
                continue;
            }

            if (!$dryRun) {
                $this->storage->delete($key);
                $deleted[] = $key;
            }
        }

        $missing = array_values(
            array_filter(
                $referenced,
                static fn(string $key): bool => !isset($storedLookup[$key]),
            )
        );

        return new MediaSweepReport(
            count($stored),
            count($referenced),
            $unreferenced,
            $retained,
            $deleted,
            $missing,
        );
    }

    /** @param list<string> $managedPrefixes */
    private function isManaged(string $key, array $managedPrefixes): bool
    {
        foreach ($managedPrefixes as $prefix) {
            $prefix = trim($prefix, '/');

            if ($prefix !== '' && str_starts_with($key, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }
}

<?php

declare(strict_types=1);

namespace App\Shared\Application\Media;

final readonly class MediaSweepReport
{
    /**
     * @param list<string> $unreferenced stored keys with no database reference
     * @param list<string> $retained unreferenced keys kept because of the grace period
     * @param list<string> $deleted unreferenced keys removed by this sweep
     * @param list<string> $missing referenced keys with no stored file
     */
    public function __construct(
        public int $storedFiles,
        public int $referencedKeys,
        public array $unreferenced,
        public array $retained,
        public array $deleted,
        public array $missing,
    ) {
    }
}

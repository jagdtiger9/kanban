<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\Media;

use App\Shared\Application\Media\MediaSweeper;
use App\Shared\Application\Storage\Exception\FileNotFoundException;
use App\Shared\Application\Storage\FileStorage;
use PHPUnit\Framework\TestCase;

final class MediaSweeperTest extends TestCase
{
    private const array MANAGED_PREFIXES = ['attachments', 'avatars'];

    private InMemoryFileStorage $storage;
    private MediaSweeper $sweeper;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->storage = new InMemoryFileStorage();
        $this->sweeper = new MediaSweeper($this->storage);
        $this->now = new \DateTimeImmutable('2026-11-18 12:00:00 UTC');
    }

    public function testDeletesOldUnreferencedFilesAndReportsMissingReferences(): void
    {
        $old = new \DateTimeImmutable('2026-11-16 12:00:00 UTC');
        $recent = new \DateTimeImmutable('2026-11-18 06:00:00 UTC');
        $this->storage->add('attachments/card/keep.pdf', 'keep', $old);
        $this->storage->add('attachments/card/orphan.pdf', 'orphan', $old);
        $this->storage->add('attachments/card/recent.pdf', 'recent', $recent);
        $this->storage->add('avatars/user/keep.webp', 'keep', $old);
        $this->storage->add('avatars/user/replaced.webp', 'replaced', $old);
        $this->storage->add('root.txt', 'unmanaged', $old);

        $report = $this->sweeper->sweep(
            [
                'avatars/user/keep.webp',
                'attachments/card/keep.pdf',
                'attachments/card/keep.pdf',
                'attachments/card/missing.pdf',
            ],
            self::MANAGED_PREFIXES,
            $this->now,
            new \DateInterval('PT24H'),
            false,
        );

        self::assertSame(6, $report->storedFiles);
        self::assertSame(3, $report->referencedKeys);
        self::assertSame(
            ['attachments/card/orphan.pdf', 'attachments/card/recent.pdf', 'avatars/user/replaced.webp'],
            $report->unreferenced,
        );
        self::assertSame(['attachments/card/recent.pdf'], $report->retained);
        self::assertSame(['attachments/card/orphan.pdf', 'avatars/user/replaced.webp'], $report->deleted);
        self::assertSame(['attachments/card/missing.pdf'], $report->missing);
        self::assertFalse($this->storage->exists('attachments/card/orphan.pdf'));
        self::assertFalse($this->storage->exists('avatars/user/replaced.webp'));
        self::assertTrue($this->storage->exists('attachments/card/recent.pdf'));
        self::assertTrue($this->storage->exists('root.txt'));
    }

    public function testDryRunReportsWithoutDeleting(): void
    {
        $old = new \DateTimeImmutable('2026-11-16 12:00:00 UTC');
        $this->storage->add('attachments/card/orphan.pdf', 'orphan', $old);

        $report = $this->sweeper->sweep([], self::MANAGED_PREFIXES, $this->now, new \DateInterval('PT24H'), true);

        self::assertSame(['attachments/card/orphan.pdf'], $report->unreferenced);
        self::assertSame([], $report->retained);
        self::assertSame([], $report->deleted);
        self::assertTrue($this->storage->exists('attachments/card/orphan.pdf'));
        self::assertSame([], $this->storage->deleted);
    }

    public function testUnknownModificationTimeIsRetained(): void
    {
        $this->storage->add('attachments/card/orphan.pdf', 'orphan', null);

        $report = $this->sweeper->sweep([], self::MANAGED_PREFIXES, $this->now, new \DateInterval('PT24H'), false);

        self::assertSame(['attachments/card/orphan.pdf'], $report->unreferenced);
        self::assertSame(['attachments/card/orphan.pdf'], $report->retained);
        self::assertSame([], $report->deleted);
        self::assertTrue($this->storage->exists('attachments/card/orphan.pdf'));
    }
}

final class InMemoryFileStorage implements FileStorage
{
    /** @var array<string, array{contents: string, modifiedAt: ?\DateTimeImmutable}> */
    private array $files = [];

    /** @var list<string> */
    public array $deleted = [];

    public function add(string $key, string $contents, ?\DateTimeImmutable $modifiedAt): void
    {
        $this->files[$key] = ['contents' => $contents, 'modifiedAt' => $modifiedAt];
    }

    public function write(string $key, string $contents): void
    {
        $this->add($key, $contents, new \DateTimeImmutable());
    }

    public function writeFromFile(string $key, string $sourcePath): void
    {
        throw new \LogicException('Not used by the sweeper.');
    }

    /**
     * @return resource
     */
    public function open(string $key)
    {
        throw new \LogicException('Not used by the sweeper.');
    }

    public function read(string $key): string
    {
        return $this->files[$key]['contents']
            ?? throw new FileNotFoundException(sprintf('Storage key "%s" does not exist.', $key));
    }

    public function exists(string $key): bool
    {
        return isset($this->files[$key]);
    }

    public function lastModified(string $key): ?\DateTimeImmutable
    {
        return $this->files[$key]['modifiedAt'] ?? null;
    }

    public function delete(string $key): void
    {
        if (!isset($this->files[$key])) {
            return;
        }

        unset($this->files[$key]);
        $this->deleted[] = $key;
    }

    public function keys(string $prefix = ''): array
    {
        $keys = array_keys($this->files);
        sort($keys);

        if ($prefix === '') {
            return $keys;
        }

        return array_values(array_filter(
            $keys,
            static fn(string $key): bool => $key === $prefix || str_starts_with($key, $prefix.'/'),
        ));
    }
}

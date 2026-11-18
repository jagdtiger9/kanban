<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Storage;

use App\Shared\Application\Storage\Exception\FileNotFoundException;
use App\Shared\Application\Storage\Exception\StorageException;
use App\Shared\Infrastructure\Storage\LocalFileStorage;
use FilesystemIterator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class LocalFileStorageTest extends TestCase
{
    private string $directory;
    private LocalFileStorage $storage;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/kanban-storage-'.bin2hex(random_bytes(8));
        $this->storage = new LocalFileStorage($this->directory);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testWritesReadsAndOpensFile(): void
    {
        $this->storage->write('attachments/abc/file.txt', 'hello');

        self::assertTrue($this->storage->exists('attachments/abc/file.txt'));
        self::assertSame('hello', $this->storage->read('attachments/abc/file.txt'));

        $handle = $this->storage->open('attachments/abc/file.txt');

        try {
            self::assertSame('hello', stream_get_contents($handle));
        } finally {
            fclose($handle);
        }
    }

    public function testOverwritesExistingKey(): void
    {
        $this->storage->write('attachments/file.txt', 'first');
        $this->storage->write('attachments/file.txt', 'second');

        self::assertSame('second', $this->storage->read('attachments/file.txt'));
    }

    public function testWriteFromFileCopiesContents(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'kanban-source-');
        self::assertNotFalse($source);
        file_put_contents($source, 'source contents');

        try {
            $this->storage->writeFromFile('avatars/user.webp', $source);

            self::assertSame('source contents', $this->storage->read('avatars/user.webp'));
            self::assertFileExists($source);
        } finally {
            @unlink($source);
        }
    }

    public function testWriteFromFileRejectsMissingSource(): void
    {
        $this->expectException(StorageException::class);

        $this->storage->writeFromFile('avatars/user.webp', $this->directory.'/missing.webp');
    }

    public function testOpenMissingFileThrows(): void
    {
        $this->expectException(FileNotFoundException::class);

        $this->storage->open('attachments/missing.txt');
    }

    public function testReadMissingFileThrows(): void
    {
        $this->expectException(FileNotFoundException::class);

        $this->storage->read('attachments/missing.txt');
    }

    public function testDeleteRemovesFile(): void
    {
        $this->storage->write('attachments/file.txt', 'hello');

        $this->storage->delete('attachments/file.txt');

        self::assertFalse($this->storage->exists('attachments/file.txt'));
    }

    public function testDeleteMissingFileIsNoOp(): void
    {
        $this->storage->delete('attachments/missing.txt');

        self::assertFalse($this->storage->exists('attachments/missing.txt'));
    }

    public function testLastModifiedReturnsFileTimestamp(): void
    {
        $this->storage->write('attachments/file.txt', 'hello');
        $timestamp = 1_700_000_000;
        touch($this->directory.'/attachments/file.txt', $timestamp);
        clearstatcache();

        $modified = $this->storage->lastModified('attachments/file.txt');

        self::assertNotNull($modified);
        self::assertSame((string) $timestamp, $modified->format('U'));
    }

    public function testLastModifiedMissingFileReturnsNull(): void
    {
        self::assertNull($this->storage->lastModified('attachments/missing.txt'));
    }

    public function testKeysReturnsEmptyListWhenDirectoryDoesNotExist(): void
    {
        self::assertSame([], $this->storage->keys());
    }

    public function testListsKeysByPrefix(): void
    {
        $this->storage->write('attachments/a.txt', 'a');
        $this->storage->write('attachments/b/c.txt', 'c');
        $this->storage->write('avatars/x.webp', 'x');
        $this->storage->write('root.txt', 'r');

        self::assertSame(
            ['attachments/a.txt', 'attachments/b/c.txt', 'avatars/x.webp', 'root.txt'],
            $this->storage->keys(),
        );
        self::assertSame(['attachments/a.txt', 'attachments/b/c.txt'], $this->storage->keys('attachments'));
        self::assertSame(['attachments/a.txt', 'attachments/b/c.txt'], $this->storage->keys('attachments/'));
        self::assertSame(['attachments/b/c.txt'], $this->storage->keys('attachments/b'));
        self::assertSame(['attachments/b/c.txt'], $this->storage->keys('attachments/b/'));
        self::assertSame([], $this->storage->keys('attachment'));
        self::assertSame([], $this->storage->keys('missing'));
    }

    #[DataProvider('unsafeKeys')]
    public function testRejectsUnsafeKeys(string $key): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->storage->write($key, 'contents');
    }

    /**
     * @return iterable<string, array{non-empty-string}>
     */
    public static function unsafeKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'absolute' => ['/etc/passwd'];
        yield 'parent' => ['../escape.txt'];
        yield 'nested parent' => ['attachments/../../escape.txt'];
        yield 'dot segment' => ['attachments/./file.txt'];
        yield 'double slash' => ['attachments//file.txt'];
        yield 'trailing slash' => ['attachments/'];
        yield 'backslash' => ['attachments\\file.txt'];
        yield 'null byte' => ["attachments/file\0.txt"];
        yield 'hidden' => ['.htaccess'];
        yield 'space' => ['attachments/my file.txt'];
    }

    public function testRejectsUnsafePrefix(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->storage->keys('../attachments');
    }

    public function testRejectsOverlongKey(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->storage->write('attachments/'.str_repeat('a', 245).'.txt', 'contents');
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($directory);
    }
}

<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Storage;

use App\Shared\Application\Storage\Exception\FileNotFoundException;
use App\Shared\Application\Storage\Exception\StorageException;
use App\Shared\Application\Storage\FileStorage;
use FilesystemIterator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class LocalFileStorage implements FileStorage
{
    private readonly string $directory;

    public function __construct(string $directory)
    {
        $this->directory = rtrim($directory, '/');

        if ($this->directory === '') {
            throw new InvalidArgumentException('Storage directory must not be empty.');
        }
    }

    public function write(string $key, string $contents): void
    {
        $path = $this->path($key);
        $this->ensureDirectory(dirname($path));
        $tmp = $this->temporaryFile(dirname($path));

        try {
            if (file_put_contents($tmp, $contents) === false) {
                throw new StorageException(sprintf('Unable to write storage key "%s".', $key));
            }

            $this->publish($tmp, $path, $key);
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    public function writeFromFile(string $key, string $sourcePath): void
    {
        if (!is_file($sourcePath) || !is_readable($sourcePath)) {
            throw new StorageException(sprintf('Source file "%s" is not readable.', $sourcePath));
        }

        $path = $this->path($key);
        $this->ensureDirectory(dirname($path));
        $tmp = $this->temporaryFile(dirname($path));

        try {
            if (!copy($sourcePath, $tmp)) {
                throw new StorageException(sprintf('Unable to copy "%s" into storage key "%s".', $sourcePath, $key));
            }

            $this->publish($tmp, $path, $key);
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    public function open(string $key)
    {
        $handle = @fopen($this->path($key), 'rb');

        if ($handle === false) {
            throw new FileNotFoundException(sprintf('Storage key "%s" does not exist.', $key));
        }

        return $handle;
    }

    public function read(string $key): string
    {
        $path = $this->path($key);

        if (!is_file($path)) {
            throw new FileNotFoundException(sprintf('Storage key "%s" does not exist.', $key));
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new StorageException(sprintf('Unable to read storage key "%s".', $key));
        }

        return $contents;
    }

    public function exists(string $key): bool
    {
        return is_file($this->path($key));
    }

    public function lastModified(string $key): ?\DateTimeImmutable
    {
        $path = $this->path($key);

        if (!is_file($path)) {
            return null;
        }

        $mtime = @filemtime($path);

        if ($mtime === false) {
            return null;
        }

        return (new \DateTimeImmutable('@' . $mtime));
    }

    public function delete(string $key): void
    {
        $path = $this->path($key);

        if (!is_file($path)) {
            return;
        }

        if (!unlink($path)) {
            throw new StorageException(sprintf('Unable to delete storage key "%s".', $key));
        }
    }

    public function keys(string $prefix = ''): array
    {
        $prefix = $this->normalizePrefix($prefix);

        if (!is_dir($this->directory)) {
            return [];
        }

        $keys = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || str_starts_with($file->getFilename(), '.')) {
                continue;
            }

            $key = substr($file->getPathname(), strlen($this->directory) + 1);

            if ($prefix !== '' && $key !== $prefix && !str_starts_with($key, $prefix . '/')) {
                continue;
            }

            $keys[] = $key;
        }

        sort($keys);

        return $keys;
    }

    private function path(string $key): string
    {
        $this->assertValidKey($key);

        return $this->directory . '/' . $key;
    }

    private function assertValidKey(string $key): void
    {
        if ($key === '' || strlen($key) > 255) {
            throw new InvalidArgumentException(sprintf('Invalid storage key "%s".', $key));
        }

        foreach (explode('/', $key) as $segment) {
            if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $segment) !== 1) {
                throw new InvalidArgumentException(sprintf('Invalid storage key "%s".', $key));
            }
        }
    }

    private function normalizePrefix(string $prefix): string
    {
        $prefix = rtrim($prefix, '/');

        if ($prefix === '') {
            return '';
        }

        $this->assertValidKey($prefix);

        return $prefix;
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new StorageException(sprintf('Unable to create storage directory "%s".', $directory));
        }
    }

    private function temporaryFile(string $directory): string
    {
        $tmp = tempnam($directory, '.tmp-');

        if ($tmp === false) {
            throw new StorageException(sprintf('Unable to create a temporary file in "%s".', $directory));
        }

        return $tmp;
    }

    private function publish(string $tmp, string $path, string $key): void
    {
        @chmod($tmp, 0664);

        if (!rename($tmp, $path)) {
            throw new StorageException(sprintf('Unable to store storage key "%s".', $key));
        }
    }
}

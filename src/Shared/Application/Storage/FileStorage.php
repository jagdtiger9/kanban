<?php

declare(strict_types=1);

namespace App\Shared\Application\Storage;

use App\Shared\Application\Storage\Exception\FileNotFoundException;
use App\Shared\Application\Storage\Exception\StorageException;

interface FileStorage
{
    /**
     * @throws StorageException
     */
    public function write(string $key, string $contents): void;

    /**
     * @throws StorageException
     */
    public function writeFromFile(string $key, string $sourcePath): void;

    /**
     * @return resource
     *
     * @throws FileNotFoundException
     */
    public function open(string $key);

    /**
     * @throws FileNotFoundException
     * @throws StorageException
     */
    public function read(string $key): string;

    public function exists(string $key): bool;

    /**
     * Null when the key does not exist or its modification time cannot be read.
     */
    public function lastModified(string $key): ?\DateTimeImmutable;

    /**
     * Deleting a missing key is a no-op so cleanup retries stay idempotent.
     *
     * @throws StorageException
     */
    public function delete(string $key): void;

    /**
     * @return list<string>
     */
    public function keys(string $prefix = ''): array;
}

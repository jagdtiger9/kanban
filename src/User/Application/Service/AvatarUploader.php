<?php

declare(strict_types=1);

namespace App\User\Application\Service;

use App\Shared\Application\Media\ImageProcessor;
use App\Shared\Application\Storage\Exception\StorageException;
use App\Shared\Application\Storage\FileStorage;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class AvatarUploader
{
    public function __construct(
        private FileStorage $storage,
        private ImageProcessor $images,
    ) {
    }

    public function store(UuidInterface $userId, UploadedFile $file): string
    {
        $storageKey = sprintf('avatars/%s/%s.webp', $userId->toString(), Uuid::uuid4()->toString());
        $avatarPath = $this->temporaryFile();

        try {
            $this->images->avatar($file->getPathname(), $avatarPath);
            $this->storage->writeFromFile($storageKey, $avatarPath);
        } finally {
            @unlink($avatarPath);
        }

        return $storageKey;
    }

    private function temporaryFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'kanban-avatar-');
        if ($path === false) {
            throw new StorageException('Unable to create a temporary file for image processing.');
        }

        return $path;
    }
}

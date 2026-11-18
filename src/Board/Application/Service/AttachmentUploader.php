<?php

declare(strict_types=1);

namespace App\Board\Application\Service;

use App\Board\Domain\Entity\Attachment;
use App\Shared\Application\Media\ImageProcessor;
use App\Shared\Application\Media\MediaPolicy;
use App\Shared\Application\Storage\Exception\StorageException;
use App\Shared\Application\Storage\FileStorage;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class AttachmentUploader
{
    public function __construct(
        private MediaPolicy $policy,
        private FileStorage $storage,
        private ImageProcessor $images,
    ) {
    }

    public function store(
        UuidInterface $projectId,
        UuidInterface $cardId,
        ?UuidInterface $commentId,
        UuidInterface $uploaderId,
        UploadedFile $file,
    ): Attachment {
        $attachmentId = Uuid::uuid4();
        $mimeType = $file->getMimeType() ?? '';
        $prefix = sprintf(
            'attachments/%s/%s/%s',
            $projectId->toString(),
            $cardId->toString(),
            $attachmentId->toString(),
        );

        $storageKey = $prefix . '.' . $this->policy->extensionFor($mimeType);
        $this->storage->writeFromFile($storageKey, $file->getPathname());

        $width = null;
        $height = null;
        $thumbnailKey = null;
        if ($this->policy->isImage($mimeType)) {
            $thumbnailPath = $this->temporaryFile();

            try {
                $metadata = $this->images->thumbnail($file->getPathname(), $thumbnailPath);
                $width = $metadata->width;
                $height = $metadata->height;
                $thumbnailKey = $prefix . '-thumbnail.webp';
                $this->storage->writeFromFile($thumbnailKey, $thumbnailPath);
            } finally {
                @unlink($thumbnailPath);
            }
        }

        return Attachment::create(
            $attachmentId,
            $cardId,
            $commentId,
            $uploaderId,
            $this->originalName($file),
            $storageKey,
            $thumbnailKey,
            $mimeType,
            (int)$file->getSize(),
            $width,
            $height,
        );
    }

    private function originalName(UploadedFile $file): string
    {
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $name = trim((string)preg_replace('/[\x00-\x1F\x7F]/', '_', $name));
        if ($name === '' || $name === '.' || $name === '..') {
            return 'file';
        }

        return mb_substr($name, 0, 255);
    }

    private function temporaryFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'kanban-media-');
        if ($path === false) {
            throw new StorageException('Unable to create a temporary file for image processing.');
        }

        return $path;
    }
}

<?php

declare(strict_types=1);

namespace App\Shared\Application\Media;

use InvalidArgumentException;

final class MediaPolicy
{
    public const int MAX_IMAGE_BYTES = 10 * 1024 * 1024;
    public const int MAX_DOCUMENT_BYTES = 25 * 1024 * 1024;
    public const int MAX_AVATAR_BYTES = 5 * 1024 * 1024;

    public const int MAX_FILES_PER_COMMENT = 5;
    public const int MAX_BYTES_PER_COMMENT = 50 * 1024 * 1024;
    public const int MAX_FILES_PER_CARD = 20;
    public const int MAX_BYTES_PER_CARD = 200 * 1024 * 1024;
    public const int MAX_FILES_PER_REQUEST = 8;
    public const int MAX_BYTES_PER_REQUEST = 175 * 1024 * 1024;

    public const int MAX_IMAGE_SIDE = 12000;
    public const int MAX_IMAGE_PIXELS = 24_000_000;
    public const int THUMBNAIL_BOX = 480;
    public const int AVATAR_SIZE = 512;
    public const int WEBP_QUALITY = 82;
    public const int TEXT_PREVIEW_BYTES = 256 * 1024;

    private const array MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'application/pdf' => 'pdf',
        'text/plain' => 'txt',
        'text/csv' => 'csv',
        'text/markdown' => 'md',
        'application/json' => 'json',
    ];

    public function accepts(string $mimeType): bool
    {
        return isset(self::MIME_EXTENSIONS[$mimeType]);
    }

    public function isImage(string $mimeType): bool
    {
        return in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true);
    }

    public function isText(string $mimeType): bool
    {
        return in_array($mimeType, ['text/plain', 'text/csv', 'text/markdown', 'application/json'], true);
    }

    public function extensionFor(string $mimeType): string
    {
        return self::MIME_EXTENSIONS[$mimeType]
            ?? throw new InvalidArgumentException(sprintf('Unsupported MIME type "%s".', $mimeType));
    }

    public function maxBytes(string $mimeType): int
    {
        return $this->isImage($mimeType) ? self::MAX_IMAGE_BYTES : self::MAX_DOCUMENT_BYTES;
    }

    public function acceptsDimensions(int $width, int $height): bool
    {
        return $width > 0
            && $height > 0
            && $width <= self::MAX_IMAGE_SIDE
            && $height <= self::MAX_IMAGE_SIDE
            && $width * $height <= self::MAX_IMAGE_PIXELS;
    }

    public function megabytes(int $bytes): int
    {
        return (int) ceil($bytes / 1024 / 1024);
    }
}

<?php

declare(strict_types=1);

namespace App\Shared\Application\Media;

use App\Shared\Application\Media\Exception\ImageProcessingException;

interface ImageProcessor
{
    /**
     * Reads the displayed dimensions, applying EXIF orientation without decoding pixels.
     *
     * @throws ImageProcessingException
     */
    public function inspect(string $path): ImageMetadata;

    /**
     * Writes a WebP thumbnail fitting inside the thumbnail box and returns the
     * source dimensions.
     *
     * @throws ImageProcessingException
     */
    public function thumbnail(string $sourcePath, string $targetPath): ImageMetadata;

    /**
     * Writes a center-cropped square WebP avatar.
     *
     * @throws ImageProcessingException
     */
    public function avatar(string $sourcePath, string $targetPath): void;
}

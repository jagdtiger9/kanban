<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Media;

use App\Shared\Application\Media\Exception\ImageProcessingException;
use App\Shared\Application\Media\ImageMetadata;
use App\Shared\Application\Media\ImageProcessor;
use App\Shared\Application\Media\MediaPolicy;
use GdImage;

final class GdImageProcessor implements ImageProcessor
{
    public function __construct(private readonly MediaPolicy $policy)
    {
    }

    public function inspect(string $path): ImageMetadata
    {
        $size = @getimagesize($path);
        if ($size === false) {
            throw new ImageProcessingException('The file is not a readable image.');
        }

        [$width, $height] = [(int)$size[0], (int)$size[1]];
        if ($this->swapsDimensions($this->readOrientation($path))) {
            [$width, $height] = [$height, $width];
        }
        $this->assertDimensions($width, $height);

        return new ImageMetadata($width, $height);
    }

    public function thumbnail(string $sourcePath, string $targetPath): ImageMetadata
    {
        $source = $this->load($sourcePath);
        $width = imagesx($source);
        $height = imagesy($source);

        $scale = min(1.0, MediaPolicy::THUMBNAIL_BOX / max($width, $height));
        $thumbnail = $this->resize(
            $source,
            max(1, (int)round($width * $scale)),
            max(1, (int)round($height * $scale)),
        );
        $this->write($thumbnail, $targetPath);

        return new ImageMetadata($width, $height);
    }

    public function avatar(string $sourcePath, string $targetPath): void
    {
        $source = $this->load($sourcePath);
        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);

        $avatar = $this->createCanvas(MediaPolicy::AVATAR_SIZE, MediaPolicy::AVATAR_SIZE);
        imagecopyresampled(
            $avatar,
            $source,
            0,
            0,
            intdiv($width - $side, 2),
            intdiv($height - $side, 2),
            MediaPolicy::AVATAR_SIZE,
            MediaPolicy::AVATAR_SIZE,
            $side,
            $side,
        );
        $this->write($avatar, $targetPath);
    }

    private function load(string $path): GdImage
    {
        $this->inspect($path);

        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new ImageProcessingException('The image could not be read.');
        }

        $image = @imagecreatefromstring($contents);
        if (!$image instanceof GdImage) {
            throw new ImageProcessingException('The image could not be decoded.');
        }
        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        return $this->orientate($image, $this->readOrientation($path));
    }

    private function orientate(GdImage $image, int $orientation): GdImage
    {
        return match ($orientation) {
            2 => $this->flip($image, IMG_FLIP_HORIZONTAL),
            3 => $this->rotate($image, 180),
            4 => $this->flip($image, IMG_FLIP_VERTICAL),
            5 => $this->flip($this->rotate($image, -90), IMG_FLIP_HORIZONTAL),
            6 => $this->rotate($image, -90),
            7 => $this->flip($this->rotate($image, -90), IMG_FLIP_VERTICAL),
            8 => $this->rotate($image, 90),
            default => $image,
        };
    }

    private function flip(GdImage $image, int $mode): GdImage
    {
        imageflip($image, $mode);

        return $image;
    }

    private function rotate(GdImage $image, float $angle): GdImage
    {
        $rotated = imagerotate($image, $angle, 0);
        if (!$rotated instanceof GdImage) {
            throw new ImageProcessingException('The image could not be rotated.');
        }
        imagealphablending($rotated, false);
        imagesavealpha($rotated, true);

        return $rotated;
    }

    private function resize(GdImage $source, int $width, int $height): GdImage
    {
        $target = $this->createCanvas($width, $height);
        imagecopyresampled(
            $target,
            $source,
            0,
            0,
            0,
            0,
            $width,
            $height,
            imagesx($source),
            imagesy($source),
        );

        return $target;
    }

    private function createCanvas(int $width, int $height): GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);
        if (!$canvas instanceof GdImage) {
            throw new ImageProcessingException('The image canvas could not be created.');
        }
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        return $canvas;
    }

    private function write(GdImage $image, string $targetPath): void
    {
        if (!@imagewebp($image, $targetPath, MediaPolicy::WEBP_QUALITY)) {
            throw new ImageProcessingException('The image could not be written.');
        }
    }

    private function readOrientation(string $path): int
    {
        if (!function_exists('exif_read_data')) {
            return 1;
        }

        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? ($exif['Orientation'] ?? 1) : 1;

        return is_int($orientation) && $orientation >= 1 && $orientation <= 8 ? $orientation : 1;
    }

    private function swapsDimensions(int $orientation): bool
    {
        return in_array($orientation, [5, 6, 7, 8], true);
    }

    private function assertDimensions(int $width, int $height): void
    {
        if (!$this->policy->acceptsDimensions($width, $height)) {
            throw new ImageProcessingException(
                sprintf(
                    'Image dimensions %d×%d exceed the %d MP / %d px side limit.',
                    $width,
                    $height,
                    intdiv(MediaPolicy::MAX_IMAGE_PIXELS, 1_000_000),
                    MediaPolicy::MAX_IMAGE_SIDE,
                )
            );
        }
    }
}

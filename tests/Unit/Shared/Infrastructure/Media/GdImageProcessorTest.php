<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Media;

use App\Shared\Application\Media\Exception\ImageProcessingException;
use App\Shared\Application\Media\MediaPolicy;
use App\Shared\Infrastructure\Media\GdImageProcessor;
use GdImage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GdImageProcessorTest extends TestCase
{
    private const array RED = [255, 0, 0];
    private const array GREEN = [0, 255, 0];
    private const array BLUE = [0, 0, 255];
    private const array WHITE = [255, 255, 255];

    private string $directory;
    private GdImageProcessor $processor;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/kanban-image-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0777, true);
        $this->processor = new GdImageProcessor(new MediaPolicy());
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testInspectReturnsDimensions(): void
    {
        $path = $this->path('photo.png');
        $this->solidPng($path, 30, 20, self::RED);

        $metadata = $this->processor->inspect($path);

        self::assertSame(30, $metadata->width);
        self::assertSame(20, $metadata->height);
    }

    public function testInspectRejectsNonImage(): void
    {
        $path = $this->path('notes.txt');
        file_put_contents($path, 'not an image');

        $this->expectException(ImageProcessingException::class);

        $this->processor->inspect($path);
    }

    public function testInspectRejectsOversizedDimensionsBeforeDecoding(): void
    {
        $path = $this->path('huge.png');
        file_put_contents($path, $this->pngHeader(13000, 13000));

        $this->expectException(ImageProcessingException::class);

        $this->processor->inspect($path);
    }

    public function testThumbnailRejectsOversizedDimensionsBeforeDecoding(): void
    {
        $path = $this->path('huge.png');
        file_put_contents($path, $this->pngHeader(13000, 13000));

        $this->expectException(ImageProcessingException::class);

        $this->processor->thumbnail($path, $this->path('huge.webp'));
    }

    #[DataProvider('thumbnailSizes')]
    public function testThumbnailFitsInsideBox(int $width, int $height, int $expectedWidth, int $expectedHeight): void
    {
        $path = $this->path('source.png');
        $this->solidPng($path, $width, $height, self::BLUE);

        $metadata = $this->processor->thumbnail($path, $this->path('thumb.webp'));

        self::assertSame($width, $metadata->width);
        self::assertSame($height, $metadata->height);
        self::assertSame([$expectedWidth, $expectedHeight], $this->imageSize($this->path('thumb.webp')));
        self::assertSame(IMAGETYPE_WEBP, exif_imagetype($this->path('thumb.webp')));
    }

    /**
     * @return iterable<string, array{int, int, int, int}>
     */
    public static function thumbnailSizes(): iterable
    {
        yield 'landscape' => [960, 480, 480, 240];
        yield 'portrait' => [480, 960, 240, 480];
        yield 'already small' => [100, 80, 100, 80];
    }

    public function testThumbnailPreservesTransparency(): void
    {
        $source = $this->path('transparent.png');
        $image = imagecreatetruecolor(100, 100);
        self::assertNotFalse($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefilledrectangle($image, 0, 0, 99, 99, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagefilledrectangle($image, 40, 40, 59, 59, imagecolorallocatealpha($image, 255, 0, 0, 0));
        imagepng($image, $source);

        $this->processor->thumbnail($source, $this->path('thumb.webp'));

        $thumbnail = imagecreatefromwebp($this->path('thumb.webp'));
        self::assertNotFalse($thumbnail);
        self::assertGreaterThan(100, $this->alphaAt($thumbnail, 0, 0));
        self::assertLessThan(30, $this->alphaAt($thumbnail, 50, 50));
    }

    public function testAvatarCenterCrops(): void
    {
        $source = $this->path('wide.png');
        $image = imagecreatetruecolor(600, 300);
        self::assertNotFalse($image);
        imagefilledrectangle($image, 0, 0, 599, 299, imagecolorallocate($image, ...self::RED));
        imagefilledrectangle($image, 150, 0, 449, 299, imagecolorallocate($image, ...self::GREEN));
        imagepng($image, $source);

        $this->processor->avatar($source, $this->path('avatar.webp'));

        self::assertSame([512, 512], $this->imageSize($this->path('avatar.webp')));
        $avatar = imagecreatefromwebp($this->path('avatar.webp'));
        self::assertNotFalse($avatar);
        foreach ([[2, 2], [509, 2], [2, 509], [509, 509], [256, 256]] as [$x, $y]) {
            $this->assertColor($avatar, $x, $y, self::GREEN);
        }
    }

    public function testAvatarUpscalesSmallImages(): void
    {
        $source = $this->path('small.png');
        $this->solidPng($source, 100, 50, self::BLUE);

        $this->processor->avatar($source, $this->path('avatar.webp'));

        self::assertSame([512, 512], $this->imageSize($this->path('avatar.webp')));
        $avatar = imagecreatefromwebp($this->path('avatar.webp'));
        self::assertNotFalse($avatar);
        $this->assertColor($avatar, 256, 256, self::BLUE);
    }

    #[DataProvider('orientations')]
    public function testThumbnailAppliesExifOrientation(int $orientation, array $expected): void
    {
        if (!function_exists('exif_read_data')) {
            self::markTestSkipped('The exif extension is not available.');
        }

        $source = $this->path('quadrants.jpg');
        $this->quadrantJpeg($source);
        $this->injectOrientation($source, $orientation);

        $metadata = $this->processor->thumbnail($source, $this->path('thumb.webp'));

        self::assertSame(32, $metadata->width);
        self::assertSame(32, $metadata->height);
        $thumbnail = imagecreatefromwebp($this->path('thumb.webp'));
        self::assertNotFalse($thumbnail);
        foreach ([[8, 8], [24, 8], [8, 24], [24, 24]] as $index => [$x, $y]) {
            $this->assertColor($thumbnail, $x, $y, $expected[$index]);
        }
    }

    /**
     * @return iterable<string, array{int, list<array{int, int, int}>}>
     */
    public static function orientations(): iterable
    {
        yield 'normal' => [1, [self::RED, self::GREEN, self::BLUE, self::WHITE]];
        yield 'flip horizontal' => [2, [self::GREEN, self::RED, self::WHITE, self::BLUE]];
        yield 'rotate 180' => [3, [self::WHITE, self::BLUE, self::GREEN, self::RED]];
        yield 'flip vertical' => [4, [self::BLUE, self::WHITE, self::RED, self::GREEN]];
        yield 'transpose' => [5, [self::RED, self::BLUE, self::GREEN, self::WHITE]];
        yield 'rotate 90 clockwise' => [6, [self::BLUE, self::RED, self::WHITE, self::GREEN]];
        yield 'transverse' => [7, [self::WHITE, self::GREEN, self::BLUE, self::RED]];
        yield 'rotate 90 counter-clockwise' => [8, [self::GREEN, self::WHITE, self::RED, self::BLUE]];
    }

    #[DataProvider('orientationDimensions')]
    public function testInspectAppliesExifOrientation(int $orientation, int $expectedWidth, int $expectedHeight): void
    {
        if (!function_exists('exif_read_data')) {
            self::markTestSkipped('The exif extension is not available.');
        }

        $path = $this->path('photo.jpg');
        $this->solidJpeg($path, 40, 20);
        $this->injectOrientation($path, $orientation);

        $metadata = $this->processor->inspect($path);

        self::assertSame($expectedWidth, $metadata->width);
        self::assertSame($expectedHeight, $metadata->height);
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function orientationDimensions(): iterable
    {
        yield 'normal' => [1, 40, 20];
        yield 'rotate 180' => [3, 40, 20];
        yield 'transpose' => [5, 20, 40];
        yield 'rotate 90 clockwise' => [6, 20, 40];
        yield 'transverse' => [7, 20, 40];
        yield 'rotate 90 counter-clockwise' => [8, 20, 40];
    }

    private function path(string $name): string
    {
        return $this->directory.'/'.$name;
    }

    /**
     * @return array{int, int}
     */
    private function imageSize(string $path): array
    {
        $size = getimagesize($path);
        self::assertNotFalse($size);

        return [(int) $size[0], (int) $size[1]];
    }

    private function solidPng(string $path, int $width, int $height, array $color): void
    {
        $image = imagecreatetruecolor($width, $height);
        self::assertNotFalse($image);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, imagecolorallocate($image, ...$color));
        imagepng($image, $path);
    }

    private function solidJpeg(string $path, int $width, int $height): void
    {
        $image = imagecreatetruecolor($width, $height);
        self::assertNotFalse($image);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, imagecolorallocate($image, ...self::WHITE));
        imagejpeg($image, $path, 100);
    }

    private function quadrantJpeg(string $path): void
    {
        $image = imagecreatetruecolor(32, 32);
        self::assertNotFalse($image);
        imagefilledrectangle($image, 0, 0, 15, 15, imagecolorallocate($image, ...self::RED));
        imagefilledrectangle($image, 16, 0, 31, 15, imagecolorallocate($image, ...self::GREEN));
        imagefilledrectangle($image, 0, 16, 15, 31, imagecolorallocate($image, ...self::BLUE));
        imagefilledrectangle($image, 16, 16, 31, 31, imagecolorallocate($image, ...self::WHITE));
        imagejpeg($image, $path, 100);
    }

    private function injectOrientation(string $path, int $orientation): void
    {
        $jpeg = (string) file_get_contents($path);

        $entry = pack('v', 0x0112).pack('v', 3).pack('V', 1).pack('v', $orientation).pack('v', 0);
        $tiff = 'II'.pack('v', 42).pack('V', 8).pack('v', 1).$entry.pack('V', 0);
        $payload = "Exif\0\0".$tiff;
        $app1 = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

        file_put_contents($path, substr($jpeg, 0, 2).$app1.substr($jpeg, 2));
    }

    private function pngHeader(int $width, int $height): string
    {
        $data = pack('N2', $width, $height)."\x08\x06\x00\x00\x00";

        return "\x89PNG\r\n\x1a\n"
            .pack('N', 13).'IHDR'.$data.pack('N', crc32('IHDR'.$data))
            .str_repeat("\0", 128);
    }

    private function alphaAt(GdImage $image, int $x, int $y): int
    {
        $color = imagecolorsforindex($image, imagecolorat($image, $x, $y));

        return $color['alpha'];
    }

    /**
     * @param array{int, int, int} $expected
     */
    private function assertColor(GdImage $image, int $x, int $y, array $expected): void
    {
        $color = imagecolorsforindex($image, imagecolorat($image, $x, $y));
        $distance = sqrt(
            ($color['red'] - $expected[0]) ** 2
            + ($color['green'] - $expected[1]) ** 2
            + ($color['blue'] - $expected[2]) ** 2,
        );

        self::assertLessThan(
            60.0,
            $distance,
            sprintf(
                'Expected RGB(%d, %d, %d) at (%d, %d), got RGB(%d, %d, %d).',
                $expected[0],
                $expected[1],
                $expected[2],
                $x,
                $y,
                $color['red'],
                $color['green'],
                $color['blue'],
            ),
        );
    }
}

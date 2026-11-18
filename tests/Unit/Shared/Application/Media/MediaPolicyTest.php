<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\Media;

use App\Shared\Application\Media\MediaPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MediaPolicyTest extends TestCase
{
    private MediaPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new MediaPolicy();
    }

    #[DataProvider('allowedTypes')]
    public function testAcceptsAllowedTypes(string $mimeType, string $extension): void
    {
        self::assertTrue($this->policy->accepts($mimeType));
        self::assertSame($extension, $this->policy->extensionFor($mimeType));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function allowedTypes(): iterable
    {
        yield 'jpeg' => ['image/jpeg', 'jpg'];
        yield 'png' => ['image/png', 'png'];
        yield 'webp' => ['image/webp', 'webp'];
        yield 'gif' => ['image/gif', 'gif'];
        yield 'pdf' => ['application/pdf', 'pdf'];
        yield 'plain text' => ['text/plain', 'txt'];
        yield 'csv' => ['text/csv', 'csv'];
        yield 'markdown' => ['text/markdown', 'md'];
        yield 'json' => ['application/json', 'json'];
    }

    #[DataProvider('rejectedTypes')]
    public function testRejectsOtherTypes(string $mimeType): void
    {
        self::assertFalse($this->policy->accepts($mimeType));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejectedTypes(): iterable
    {
        yield 'svg' => ['image/svg+xml'];
        yield 'html' => ['text/html'];
        yield 'xml' => ['application/xml'];
        yield 'javascript' => ['text/javascript'];
        yield 'php' => ['text/x-php'];
        yield 'zip' => ['application/zip'];
        yield 'binary' => ['application/octet-stream'];
        yield 'empty' => [''];
    }

    public function testExtensionForRejectedTypeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->policy->extensionFor('image/svg+xml');
    }

    #[DataProvider('imageTypes')]
    public function testRecognizesImages(string $mimeType, bool $isImage): void
    {
        self::assertSame($isImage, $this->policy->isImage($mimeType));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function imageTypes(): iterable
    {
        yield 'jpeg' => ['image/jpeg', true];
        yield 'png' => ['image/png', true];
        yield 'webp' => ['image/webp', true];
        yield 'gif' => ['image/gif', true];
        yield 'pdf' => ['application/pdf', false];
        yield 'text' => ['text/plain', false];
        yield 'json' => ['application/json', false];
    }

    #[DataProvider('textTypes')]
    public function testRecognizesTextTypes(string $mimeType, bool $isText): void
    {
        self::assertSame($isText, $this->policy->isText($mimeType));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function textTypes(): iterable
    {
        yield 'plain text' => ['text/plain', true];
        yield 'csv' => ['text/csv', true];
        yield 'markdown' => ['text/markdown', true];
        yield 'json' => ['application/json', true];
        yield 'pdf' => ['application/pdf', false];
        yield 'image' => ['image/png', false];
        yield 'unknown' => ['application/zip', false];
    }

    public function testImageAndDocumentSizeLimits(): void
    {
        self::assertSame(10 * 1024 * 1024, $this->policy->maxBytes('image/png'));
        self::assertSame(25 * 1024 * 1024, $this->policy->maxBytes('application/pdf'));
        self::assertSame(25 * 1024 * 1024, $this->policy->maxBytes('text/plain'));
    }

    #[DataProvider('dimensions')]
    public function testDimensionLimits(int $width, int $height, bool $accepted): void
    {
        self::assertSame($accepted, $this->policy->acceptsDimensions($width, $height));
    }

    /**
     * @return iterable<string, array{int, int, bool}>
     */
    public static function dimensions(): iterable
    {
        yield 'full camera frame' => [6000, 4000, true];
        yield 'one pixel over the megapixel cap' => [6001, 4000, false];
        yield 'wide panorama under the cap' => [12000, 1000, true];
        yield 'side over the cap' => [12001, 100, false];
        yield 'zero width' => [0, 100, false];
        yield 'negative height' => [100, -1, false];
    }

    public function testMegabytesRoundsUp(): void
    {
        self::assertSame(10, $this->policy->megabytes(10 * 1024 * 1024));
        self::assertSame(25, $this->policy->megabytes(25 * 1024 * 1024));
        self::assertSame(1, $this->policy->megabytes(1));
    }
}

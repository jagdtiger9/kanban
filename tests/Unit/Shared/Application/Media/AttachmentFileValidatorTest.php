<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\Media;

use App\Shared\Application\Media\AttachmentFile;
use App\Shared\Application\Media\AttachmentFileValidator;
use App\Shared\Application\Media\MediaPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<AttachmentFileValidator>
 */
final class AttachmentFileValidatorTest extends ConstraintValidatorTestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/kanban-upload-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);

        parent::tearDown();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new AttachmentFileValidator(new MediaPolicy());
    }

    public function testValidSingleFilePasses(): void
    {
        $this->validateWith($this->uploaded('report.pdf', $this->pdfContents()));

        $this->assertNoViolation();
    }

    public function testNullPasses(): void
    {
        $this->validateWith(null);

        $this->assertNoViolation();
    }

    #[DataProvider('validFiles')]
    public function testValidFilesPass(string $name, string $contents): void
    {
        $this->validateWith([$this->uploaded($name, $contents)]);

        $this->assertNoViolation();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validFiles(): iterable
    {
        yield 'png' => ['image.png', self::pngBytes()];
        yield 'plain text' => ['notes.txt', 'hello world'];
        yield 'json' => ['data.json', '{"key":"value"}'];
        yield 'csv' => ['rows.csv', "name,value\nfoo,1\n"];
    }

    public function testNonUploadedValueIsRejected(): void
    {
        $constraint = $this->validateWith('/etc/passwd');

        $this->buildViolation($constraint->invalidMessage)
            ->atPath('')
            ->assertRaised();
    }

    public function testFailedUploadIsRejected(): void
    {
        $file = new UploadedFile($this->path('broken.txt'), 'broken.txt', null, UPLOAD_ERR_INI_SIZE, true);
        $constraint = $this->validateWith($file);

        $this->buildViolation($constraint->invalidMessage)
            ->atPath('')
            ->assertRaised();
    }

    public function testEmptyFileIsRejected(): void
    {
        $constraint = $this->validateWith($this->uploaded('empty.txt', ''));

        $this->buildViolation($constraint->emptyMessage)
            ->atPath('')
            ->assertRaised();
    }

    #[DataProvider('rejectedFiles')]
    public function testDisallowedTypeIsRejected(string $name, string $contents): void
    {
        $constraint = $this->validateWith($this->uploaded($name, $contents));

        $this->buildViolation($constraint->typeMessage)
            ->atPath('')
            ->assertRaised();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function rejectedFiles(): iterable
    {
        yield 'php' => ['script.php', '<?php echo "hi";'];
        yield 'svg' => ['image.svg', '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>'];
        yield 'html' => ['page.html', '<!DOCTYPE html><html><body>hi</body></html>'];
        yield 'zip' => ['archive.zip', "PK\x03\x04".str_repeat("\0", 64)];
    }

    public function testOversizedImageIsRejected(): void
    {
        $path = $this->path('large.png');
        file_put_contents($path, self::pngBytes());
        $handle = fopen($path, 'r+b');
        self::assertNotFalse($handle);
        ftruncate($handle, MediaPolicy::MAX_IMAGE_BYTES + 1);
        fclose($handle);

        $constraint = $this->validateWith(new UploadedFile($path, 'large.png', null, null, true));

        $this->buildViolation($constraint->sizeMessage)
            ->atPath('')
            ->setParameter('{{ limit }}', '10')
            ->assertRaised();
    }

    public function testOversizedDocumentIsRejected(): void
    {
        $path = $this->path('large.pdf');
        $handle = fopen($path, 'w+b');
        self::assertNotFalse($handle);
        fwrite($handle, $this->pdfContents());
        ftruncate($handle, MediaPolicy::MAX_DOCUMENT_BYTES + 1);
        fclose($handle);

        $constraint = $this->validateWith(new UploadedFile($path, 'large.pdf', null, null, true));

        $this->buildViolation($constraint->sizeMessage)
            ->atPath('')
            ->setParameter('{{ limit }}', '25')
            ->assertRaised();
    }

    public function testOversizedDimensionsAreRejected(): void
    {
        $constraint = $this->validateWith($this->uploaded('huge.png', self::pngHeader(13000, 13000)));

        $this->buildViolation($constraint->dimensionsMessage)
            ->atPath('')
            ->setParameter('{{ megapixels }}', '24')
            ->setParameter('{{ side }}', '12000')
            ->assertRaised();
    }

    public function testCorruptImageIsRejected(): void
    {
        $constraint = $this->validateWith($this->uploaded(
            'broken.jpg',
            "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00",
        ));

        $this->buildViolation($constraint->invalidMessage)
            ->atPath('')
            ->assertRaised();
    }

    public function testViolationPathPointsToInvalidArrayEntry(): void
    {
        $constraint = $this->validateWith([
            $this->uploaded('report.pdf', $this->pdfContents()),
            $this->uploaded('script.php', '<?php echo "hi";'),
        ]);

        $this->buildViolation($constraint->typeMessage)
            ->atPath('[1]')
            ->assertRaised();
    }

    public function testTooManyFilesAreRejected(): void
    {
        $constraint = $this->validateWith([
            $this->uploaded('one.txt', 'one'),
            $this->uploaded('two.txt', 'two'),
        ], new AttachmentFile(maxFiles: 1));

        $this->buildViolation($constraint->countMessage)
            ->atPath('')
            ->setParameter('{{ limit }}', '1')
            ->assertRaised();
    }

    public function testTotalSizeIsRejected(): void
    {
        $constraint = $this->validateWith([
            $this->uploaded('one.txt', str_repeat('a', 6)),
            $this->uploaded('two.txt', str_repeat('b', 6)),
        ], new AttachmentFile(maxTotalBytes: 10));

        $this->buildViolation($constraint->totalSizeMessage)
            ->atPath('')
            ->setParameter('{{ limit }}', '1')
            ->assertRaised();
    }

    public function testImagesOnlyAcceptsImages(): void
    {
        $this->validateWith(
            $this->uploaded('image.png', self::pngBytes()),
            new AttachmentFile(maxFiles: 1, maxBytes: MediaPolicy::MAX_AVATAR_BYTES, imagesOnly: true),
        );

        $this->assertNoViolation();
    }

    #[DataProvider('nonImageFiles')]
    public function testImagesOnlyRejectsNonImages(string $name, string $contents): void
    {
        $constraint = $this->validateWith(
            $this->uploaded($name, $contents),
            new AttachmentFile(
                maxFiles: 1,
                maxBytes: MediaPolicy::MAX_AVATAR_BYTES,
                imagesOnly: true,
                typeMessage: 'This file must be an image.',
            ),
        );

        $this->buildViolation($constraint->typeMessage)
            ->atPath('')
            ->assertRaised();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function nonImageFiles(): iterable
    {
        yield 'plain text' => ['notes.txt', 'hello world'];
        yield 'pdf' => ['report.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF"];
        yield 'json' => ['data.json', '{"key":"value"}'];
    }

    public function testExplicitMaxBytesOverridesTheTypeLimit(): void
    {
        $path = $this->path('large.txt');
        $handle = fopen($path, 'w+b');
        self::assertNotFalse($handle);
        fwrite($handle, str_repeat('a', 1024));
        ftruncate($handle, MediaPolicy::MAX_AVATAR_BYTES + 1);
        fclose($handle);

        $constraint = $this->validateWith(
            new UploadedFile($path, 'large.txt', null, null, true),
            new AttachmentFile(maxBytes: MediaPolicy::MAX_AVATAR_BYTES),
        );

        $this->buildViolation($constraint->sizeMessage)
            ->atPath('')
            ->setParameter('{{ limit }}', '5')
            ->assertRaised();
    }

    /**
     * @param array<int|string, mixed>|mixed $value
     */
    private function validateWith(mixed $value, ?AttachmentFile $constraint = null): AttachmentFile
    {
        $constraint ??= new AttachmentFile();
        $this->constraint = $constraint;
        $this->context->setConstraint($constraint);
        $this->context->setNode($this->value, $this->object, $this->metadata, '');
        $this->validator->validateInContext($value, $constraint, $this->context);

        return $constraint;
    }

    private function uploaded(string $name, string $contents): UploadedFile
    {
        $path = $this->path($name);
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function path(string $name): string
    {
        return $this->directory.'/'.$name;
    }

    private function pdfContents(): string
    {
        return implode("\n", [
            '%PDF-1.4',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Kids [] /Count 0 >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
        ]);
    }

    private static function pngBytes(): string
    {
        $image = imagecreatetruecolor(2, 2);
        self::assertNotFalse($image);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    private static function pngHeader(int $width, int $height): string
    {
        $data = pack('N2', $width, $height)."\x08\x06\x00\x00\x00";

        return "\x89PNG\r\n\x1a\n"
            .pack('N', 13).'IHDR'.$data.pack('N', crc32('IHDR'.$data))
            .str_repeat("\0", 128);
    }
}

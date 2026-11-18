<?php

declare(strict_types=1);

namespace App\Shared\Application\Media;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class AttachmentFileValidator extends ConstraintValidator
{
    public function __construct(private readonly MediaPolicy $policy)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof AttachmentFile) {
            throw new UnexpectedTypeException($constraint, AttachmentFile::class);
        }

        if ($value === null) {
            return;
        }

        $isList = is_array($value);
        $files = $isList ? array_values($value) : [$value];

        if (count($files) > $constraint->maxFiles) {
            $this->addViolation($constraint->countMessage, null, [
                '{{ limit }}' => (string) $constraint->maxFiles,
            ]);

            return;
        }

        $totalBytes = 0;
        foreach ($files as $index => $file) {
            $totalBytes += $this->validateFile($file, $constraint, $isList ? $index : null);
        }

        if ($totalBytes > $constraint->maxTotalBytes) {
            $this->addViolation($constraint->totalSizeMessage, null, [
                '{{ limit }}' => (string) $this->policy->megabytes($constraint->maxTotalBytes),
            ]);
        }
    }

    private function validateFile(mixed $file, AttachmentFile $constraint, ?int $index): int
    {
        $path = $index === null ? null : '['.$index.']';

        if (!$file instanceof UploadedFile) {
            $this->addViolation($constraint->invalidMessage, $path);

            return 0;
        }

        if (!$file->isValid()) {
            $this->addViolation($constraint->invalidMessage, $path);

            return 0;
        }

        $size = $file->getSize();
        if ($size === false) {
            $this->addViolation($constraint->invalidMessage, $path);

            return 0;
        }

        if ($size === 0) {
            $this->addViolation($constraint->emptyMessage, $path);

            return 0;
        }

        $mimeType = $file->getMimeType() ?? '';
        if (!$this->policy->accepts($mimeType) || ($constraint->imagesOnly && !$this->policy->isImage($mimeType))) {
            $this->addViolation($constraint->typeMessage, $path);

            return $size;
        }

        $maxBytes = $constraint->maxBytes ?? $this->policy->maxBytes($mimeType);
        if ($size > $maxBytes) {
            $this->addViolation($constraint->sizeMessage, $path, [
                '{{ limit }}' => (string) $this->policy->megabytes($maxBytes),
            ]);

            return $size;
        }

        if ($this->policy->isImage($mimeType)) {
            $dimensions = @getimagesize($file->getPathname());
            if ($dimensions === false) {
                $this->addViolation($constraint->invalidMessage, $path);
            } elseif (!$this->policy->acceptsDimensions((int) $dimensions[0], (int) $dimensions[1])) {
                $this->addViolation($constraint->dimensionsMessage, $path, [
                    '{{ megapixels }}' => (string) intdiv(MediaPolicy::MAX_IMAGE_PIXELS, 1_000_000),
                    '{{ side }}' => (string) MediaPolicy::MAX_IMAGE_SIDE,
                ]);
            }
        }

        return $size;
    }

    /**
     * @param array<string, string> $parameters
     */
    private function addViolation(string $message, ?string $path, array $parameters = []): void
    {
        $builder = $this->context->buildViolation($message);
        foreach ($parameters as $key => $value) {
            $builder->setParameter($key, $value);
        }
        if ($path !== null) {
            $builder->atPath($path);
        }
        $builder->addViolation();
    }
}

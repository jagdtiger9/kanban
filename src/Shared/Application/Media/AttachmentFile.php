<?php

declare(strict_types=1);

namespace App\Shared\Application\Media;

use Attribute;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class AttachmentFile extends Constraint
{
    #[HasNamedArguments]
    public function __construct(
        public int $maxFiles = MediaPolicy::MAX_FILES_PER_REQUEST,
        public int $maxTotalBytes = MediaPolicy::MAX_BYTES_PER_REQUEST,
        public ?int $maxBytes = null,
        public bool $imagesOnly = false,
        public string $typeMessage = 'This file type is not allowed.',
        public string $sizeMessage = 'The file is too large (max {{ limit }} MB).',
        public string $emptyMessage = 'The file is empty.',
        public string $invalidMessage = 'The file could not be uploaded or read.',
        public string $dimensionsMessage = 'The image is too large (max {{ megapixels }} MP, {{ side }} px per side).',
        public string $countMessage = 'Too many files (max {{ limit }}).',
        public string $totalSizeMessage = 'The selected files are too large in total (max {{ limit }} MB).',
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(groups: $groups, payload: $payload);
    }

    public function validatedBy(): string
    {
        return AttachmentFileValidator::class;
    }
}

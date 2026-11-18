<?php

declare(strict_types=1);

namespace App\Board\Application\Message\Command;

use Aljerom\SymfonyBoilerplate\CQRS\Command;
use App\Shared\Application\Media\AttachmentFile;
use App\Shared\Application\Media\MediaPolicy;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

final class AddCardAttachmentCommand implements Command
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $cardId,
        #[Assert\NotNull]
        #[AttachmentFile(maxFiles: 1, maxTotalBytes: MediaPolicy::MAX_DOCUMENT_BYTES)]
        public ?UploadedFile $file = null,
        public string $actorId = '',
    ) {
    }
}

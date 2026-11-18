<?php

declare(strict_types=1);

namespace App\Board\Application\Message\Command;

use Aljerom\SymfonyBoilerplate\CQRS\Command;
use App\Shared\Application\Media\AttachmentFile;
use App\Shared\Application\Media\MediaPolicy;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateCommentCommand implements Command
{
    /**
     * @param array<int|string, mixed> $attachments
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $cardId,
        #[Assert\NotBlank]
        #[Assert\Length(max: 10000)]
        public string $body,
        #[AttachmentFile(maxFiles: MediaPolicy::MAX_FILES_PER_COMMENT, maxTotalBytes: MediaPolicy::MAX_BYTES_PER_COMMENT)]
        public array $attachments = [],
        public string $actorId = '',
    ) {
    }
}

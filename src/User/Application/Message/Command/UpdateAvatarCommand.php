<?php

declare(strict_types=1);

namespace App\User\Application\Message\Command;

use Aljerom\SymfonyBoilerplate\CQRS\Command;
use App\Shared\Application\Media\AttachmentFile;
use App\Shared\Application\Media\MediaPolicy;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateAvatarCommand implements Command
{
    public function __construct(
        #[Assert\NotNull]
        #[AttachmentFile(
            maxFiles: 1,
            maxTotalBytes: MediaPolicy::MAX_AVATAR_BYTES,
            maxBytes: MediaPolicy::MAX_AVATAR_BYTES,
            imagesOnly: true,
        )]
        public ?UploadedFile $file = null,
        public string $actorId = '',
    ) {
    }
}

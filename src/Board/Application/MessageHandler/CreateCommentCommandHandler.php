<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Message\Command\CreateCommentCommand;
use App\Board\Application\Service\AttachmentUploader;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\Comment;
use App\Board\Domain\Exception\AttachmentLimitException;
use App\Board\Domain\Repository\AttachmentRepository;
use App\Board\Domain\Repository\CommentRepository;
use App\Shared\Application\Media\MediaPolicy;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateCommentCommandHandler
{
    public function __construct(
        private CardGuard $guard,
        private CommentRepository $commentRepository,
        private AttachmentRepository $attachmentRepository,
        private AttachmentUploader $uploader,
    ) {
    }

    /**
     * @return array{comment: Comment, attachments: list<\App\Board\Domain\Entity\Attachment>}
     */
    public function __invoke(CreateCommentCommand $command): array
    {
        $actorId = Uuid::fromString($command->actorId);
        $cardId = Uuid::fromString($command->cardId);

        $card = $this->guard->requireCard($cardId, $actorId);
        $projectId = $this->guard->resolveBoard($card)->projectId();

        /** @var list<UploadedFile> $files */
        $files = array_values($command->attachments);
        $this->assertCardCapacity($cardId, $files);

        $comment = Comment::create(Uuid::uuid4(), $cardId, $actorId, $command->body);
        $this->commentRepository->save($comment);

        $attachments = [];
        foreach ($files as $file) {
            $attachment = $this->uploader->store($projectId, $cardId, $comment->id(), $actorId, $file);
            $this->attachmentRepository->save($attachment);
            $attachments[] = $attachment;
        }

        return ['comment' => $comment, 'attachments' => $attachments];
    }

    /**
     * @param list<UploadedFile> $files
     */
    private function assertCardCapacity(UuidInterface $cardId, array $files): void
    {
        if ($files === []) {
            return;
        }

        $usage = $this->attachmentRepository->usageByCard($cardId);
        $bytes = array_sum(array_map(
            static fn(UploadedFile $file): int => (int) $file->getSize(),
            $files,
        ));

        if ($usage['files'] + count($files) > MediaPolicy::MAX_FILES_PER_CARD
            || $usage['bytes'] + $bytes > MediaPolicy::MAX_BYTES_PER_CARD) {
            throw new AttachmentLimitException('This card has reached its attachment limit');
        }
    }
}

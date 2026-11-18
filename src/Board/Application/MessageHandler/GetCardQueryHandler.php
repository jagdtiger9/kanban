<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Message\Query\GetCardQuery;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\Attachment;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\CardActivity;
use App\Board\Domain\Entity\Comment;
use App\Board\Domain\Repository\AttachmentRepository;
use App\Board\Domain\Repository\CardActivityRepository;
use App\Board\Domain\Repository\CommentRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetCardQueryHandler
{
    public function __construct(
        private CardGuard $guard,
        private CommentRepository $commentRepository,
        private AttachmentRepository $attachmentRepository,
        private CardActivityRepository $activityRepository,
    ) {
    }

    /**
     * @return array{card: Card, comments: list<Comment>, attachments: list<Attachment>, activities: list<CardActivity>}
     */
    public function __invoke(GetCardQuery $query): array
    {
        $card = $this->guard->requireCard(Uuid::fromString($query->cardId), Uuid::fromString($query->actorId));

        return [
            'card' => $card,
            'comments' => $this->commentRepository->findByCard($card->id()),
            'attachments' => $this->attachmentRepository->findByCard($card->id()),
            'activities' => $this->activityRepository->findByCard($card->id()),
        ];
    }
}

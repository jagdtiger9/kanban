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
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\CommentRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetCardQueryHandler
{
    public function __construct(
        private CardGuard $guard,
        private CardRepository $cardRepository,
        private CommentRepository $commentRepository,
        private AttachmentRepository $attachmentRepository,
        private CardActivityRepository $activityRepository,
    ) {
    }

    /**
     * @return array{
     *     card: Card,
     *     linkedCard: ?Card,
     *     comments: list<Comment>,
     *     attachments: list<Attachment>,
     *     activities: list<CardActivity>,
     *     timeline: list<Comment|CardActivity>,
     * }
     */
    public function __invoke(GetCardQuery $query): array
    {
        $card = $this->guard->requireCard(Uuid::fromString($query->cardId), Uuid::fromString($query->actorId));
        $comments = $this->commentRepository->findByCard($card->id());
        $activities = $this->activityRepository->findByCard($card->id());

        return [
            'card' => $card,
            'linkedCard' => $card->linkedCardId() === null ? null : $this->cardRepository->findById($card->linkedCardId()),
            'comments' => $comments,
            'attachments' => $this->attachmentRepository->findByCard($card->id()),
            'activities' => $activities,
            'timeline' => $this->timeline($comments, $activities),
        ];
    }

    /**
     * Comment events duplicate the comment itself, which is already a feed entry.
     *
     * @param list<Comment> $comments
     * @param list<CardActivity> $activities
     *
     * @return list<Comment|CardActivity>
     */
    private function timeline(array $comments, array $activities): array
    {
        $entries = $comments;
        foreach ($activities as $activity) {
            if (!in_array($activity->type(), ['comment_added', 'comment_edited'], true)) {
                $entries[] = $activity;
            }
        }

        usort(
            $entries,
            static fn(Comment|CardActivity $a, Comment|CardActivity $b): int => ($b->createdAt() <=> $a->createdAt())
                ?: strcmp($b->id()->toString(), $a->id()->toString()),
        );

        return $entries;
    }
}

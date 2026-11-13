<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\Comment;
use App\Board\Domain\Exception\CardForbiddenException;
use App\Board\Domain\Exception\CardNotFoundException;
use App\Board\Domain\Exception\CommentForbiddenException;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use App\Board\Domain\Repository\CommentRepository;
use App\Board\Domain\Repository\LabelRepository;
use App\Project\Application\Service\MembershipGuard;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Exception\ProjectForbiddenException;
use App\Project\Domain\Repository\ProjectRepository;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * HTTP edge layer over the guard predicates: denies before the message handlers run.
 * Global-admin checks read the live user, never the frozen token roles.
 *
 * Unknown subjects pass through: the handlers are authoritative and answer 404.
 */
final class ProjectAccessVoter extends Voter
{
    public const PROJECT_MEMBER = 'PROJECT_MEMBER';
    public const PROJECT_ADMIN = 'PROJECT_ADMIN';
    public const CARD_EDITOR = 'CARD_EDITOR';
    public const COMMENT_EDITOR = 'COMMENT_EDITOR';
    public const COMMENT_MANAGER = 'COMMENT_MANAGER';
    public const GLOBAL_ADMIN = 'GLOBAL_ADMIN';

    private const ATTRIBUTES = [
        self::PROJECT_MEMBER,
        self::PROJECT_ADMIN,
        self::CARD_EDITOR,
        self::COMMENT_EDITOR,
        self::COMMENT_MANAGER,
        self::GLOBAL_ADMIN,
    ];

    public function __construct(
        private ProjectRepository $projectRepository,
        private ColumnRepository $columnRepository,
        private BoardRepository $boardRepository,
        private LabelRepository $labelRepository,
        private CardRepository $cardRepository,
        private CommentRepository $commentRepository,
        private MembershipGuard $membershipGuard,
        private CardGuard $cardGuard,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, self::ATTRIBUTES, true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof SecurityUser) {
            return false;
        }

        $actorId = $user->user()->id();

        try {
            return match ($attribute) {
                self::GLOBAL_ADMIN => $user->user()->isGlobalAdmin(),
                self::PROJECT_MEMBER => $this->canAccessProject($subject, $actorId, false),
                self::PROJECT_ADMIN => $this->canAccessProject($subject, $actorId, true),
                self::CARD_EDITOR => $this->canEditCard($subject, $actorId),
                self::COMMENT_EDITOR => $this->canEditComment($subject, $actorId),
                self::COMMENT_MANAGER => $this->canDeleteComment($subject, $actorId),
                default => false,
            };
        } catch (CardForbiddenException|CommentForbiddenException|ProjectForbiddenException) {
            return false;
        }
    }

    private function canAccessProject(mixed $subject, UuidInterface $actorId, bool $requireAdmin): bool
    {
        $projectId = $this->projectIdOf($subject);
        if ($projectId === null) {
            // Unknown subject: nothing to protect — the handler answers 404
            return true;
        }

        $role = $this->membershipGuard->effectiveRole($projectId, $actorId);

        return $requireAdmin ? $role === MemberRole::Admin : $role !== null;
    }

    private function canEditCard(mixed $subject, UuidInterface $actorId): bool
    {
        $cardId = $this->subjectId($subject);
        if ($cardId === null) {
            return true;
        }

        try {
            $this->cardGuard->requireCardEditable($cardId, $actorId);

            return true;
        } catch (CardNotFoundException) {
            // Unknown card: the handler answers 404
            return true;
        } catch (CardForbiddenException|ProjectForbiddenException) {
            return false;
        }
    }

    private function canEditComment(mixed $subject, UuidInterface $actorId): bool
    {
        $comment = $this->commentOf($subject);
        if ($comment === null) {
            // Unknown comment: the handler answers 404
            return true;
        }

        try {
            $this->cardGuard->requireCommentEditable($comment, $actorId);

            return true;
        } catch (CommentForbiddenException|ProjectForbiddenException) {
            return false;
        }
    }

    private function canDeleteComment(mixed $subject, UuidInterface $actorId): bool
    {
        $comment = $this->commentOf($subject);
        if ($comment === null) {
            // Unknown comment: the handler answers 404
            return true;
        }

        try {
            $this->cardGuard->requireCommentDeletable($comment, $actorId);

            return true;
        } catch (CommentForbiddenException|ProjectForbiddenException) {
            return false;
        }
    }

    private function commentOf(mixed $subject): ?Comment
    {
        $commentId = $this->subjectId($subject);

        return $commentId !== null ? $this->commentRepository->findById($commentId) : null;
    }

    // Subjects arrive as raw id strings from route parameters with no kind attached;
    // the owning project is resolved by trying the known entity kinds in turn.
    private function projectIdOf(mixed $subject): ?UuidInterface
    {
        $id = $this->subjectId($subject);
        if ($id === null) {
            return null;
        }

        if ($this->projectRepository->findById($id) !== null) {
            return $id;
        }
        $column = $this->columnRepository->findById($id);
        if ($column !== null) {
            return $this->boardRepository->findById($column->boardId())?->projectId();
        }
        $label = $this->labelRepository->findById($id);
        if ($label !== null) {
            return $this->boardRepository->findById($label->boardId())?->projectId();
        }
        $card = $this->cardRepository->findById($id);
        if ($card !== null) {
            return $this->cardGuard->resolveBoard($card)->projectId();
        }

        return null;
    }

    private function subjectId(mixed $subject): ?UuidInterface
    {
        if (!\is_string($subject)) {
            return null;
        }

        try {
            return Uuid::fromString($subject);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}

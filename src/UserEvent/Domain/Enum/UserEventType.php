<?php

declare(strict_types=1);

namespace App\UserEvent\Domain\Enum;

enum UserEventType: string
{
    case CardCreated = 'card.created';
    case CardAssigned = 'card.assigned';
    case CardUnassigned = 'card.unassigned';
    case CardExecutorAssigned = 'card.executor_assigned';
    case CardExecutorUnassigned = 'card.executor_unassigned';
    case CommentCreated = 'comment.created';
    case ProjectMemberRoleChanged = 'project.member_role_changed';
    case ProjectArchived = 'project.archived';
    case ProjectRestored = 'project.restored';
}

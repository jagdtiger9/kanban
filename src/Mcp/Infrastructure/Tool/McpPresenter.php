<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Tool;

use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Label;
use App\User\Domain\Entity\User;

/**
 * Agent-oriented shapes: names next to ids, so the agent can report without extra calls.
 */
final class McpPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function card(Card $card, bool $withDescription, bool $canEdit): array
    {
        $payload = [
            'id' => (string)$card->id(),
            // may the user change title/description (creator or admin); everything else
            // (move, labels, assignees, due date, comments) is open to any member
            'canEdit' => $canEdit,
            'columnId' => (string)$card->columnId(),
            'title' => $card->title(),
            'position' => $card->position(),
            'dueDate' => $card->dueDate()?->format('Y-m-d'),
            'labels' => array_map(
                static fn(Label $label): array => ['id' => (string)$label->id(), 'name' => $label->name()],
                array_values($card->labels()->toArray()),
            ),
            'assignees' => array_map(
                static fn(User $user): array => ['id' => (string)$user->id(), 'name' => $user->displayName()],
                array_values($card->assignees()->toArray()),
            ),
            'creatorId' => (string)$card->creatorId(),
            'updatedAt' => $card->updatedAt()->format(\DateTimeInterface::ATOM),
            'archived' => $card->isArchived(),
        ];

        if ($withDescription) {
            $payload['description'] = $card->description();
        }

        return $payload;
    }
}

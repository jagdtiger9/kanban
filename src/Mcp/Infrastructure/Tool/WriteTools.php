<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Tool;

use App\Board\Application\Message\Command\CreateCardCommand;
use App\Board\Application\Message\Command\CreateCommentCommand;
use App\Board\Application\Message\Command\MoveCardCommand;
use App\Board\Application\Message\Command\SetCardAssigneesCommand;
use App\Board\Application\Message\Command\SetCardDueDateCommand;
use App\Board\Application\Message\Command\SetCardLabelsCommand;
use App\Board\Application\Message\Command\UpdateCardContentCommand;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Comment;
use App\Board\Domain\Entity\Label;
use App\Board\Domain\Exception\ColumnNotFoundException;
use App\Board\Domain\Exception\LabelNotFoundException;
use App\Project\Domain\Exception\ProjectMemberNotFoundException;
use App\User\Domain\Entity\User;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\ToolAnnotations;

/**
 * One tool = one change, applied fully or not at all. Lists (labels, assignees) change one
 * item at a time, so a call never wipes values the agent did not mention. Same commands as
 * the UI: same rights, activity log and realtime refresh.
 */
final class WriteTools
{
    public function __construct(
        private McpToolRunner $runner,
        private CardContext $context,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'create_card',
        description: 'Create a card at the end of a column; any project member may. Column ids come from get_board. If the user did not name the project or column, ask first and propose the most fitting one (usually To Do; Done for already finished work). Check the board for an existing card with the same or a similar title first; if there is one, ask before creating another.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false),
    )]
    public function createCard(
        #[Schema(description: 'Target column id from get_board', format: 'uuid')]
        string $columnId,
        #[Schema(description: 'Card title, up to 255 characters')]
        string $title,
        #[Schema(description: 'Markdown description')]
        string $description = '',
    ): array {
        $actorId = $this->runner->actorId();
        try {
            /** @var Card $card */
            $card = $this->runner->command(new CreateCardCommand($columnId, $title, $description, $actorId));
        } catch (ToolCallException $e) {
            if ($e->getPrevious() instanceof ColumnNotFoundException) {
                throw new ToolCallException('Column not found. Take a column id from get_board.', previous: $e);
            }
            throw $e;
        }

        return $this->cardResult($card, $actorId, true);
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'rename_card',
        description: 'Change the card title. Only the card creator or a project admin may (see canEdit in get_board / get_card).',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function renameCard(
        #[Schema(description: 'Card id', format: 'uuid')]
        string $cardId,
        #[Schema(description: 'New title, up to 255 characters')]
        string $title,
    ): array {
        $actorId = $this->runner->actorId();
        $card = $this->context->card($cardId, $actorId);
        $this->runner->command(new UpdateCardContentCommand($cardId, $title, $card->description(), $actorId));

        return $this->cardResult($this->context->card($cardId, $actorId), $actorId, false);
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'set_description',
        description: 'Replace the whole card description ("" clears it). To add text, use append_to_description. Only the card creator or a project admin may. If you wrote the text yourself, show it to the user first.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true),
    )]
    public function setDescription(
        #[Schema(description: 'Card id', format: 'uuid')]
        string $cardId,
        #[Schema(description: 'New full markdown description')]
        string $description,
    ): array {
        $actorId = $this->runner->actorId();
        $card = $this->context->card($cardId, $actorId);
        $this->runner->command(new UpdateCardContentCommand($cardId, $card->title(), $description, $actorId));

        return $this->cardResult($this->context->card($cardId, $actorId), $actorId, true);
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'append_to_description',
        description: 'Add text to the end of the card description, keeping the existing text (separated by a blank line). Only the card creator or a project admin may.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false),
    )]
    public function appendToDescription(
        #[Schema(description: 'Card id', format: 'uuid')]
        string $cardId,
        #[Schema(description: 'Markdown text to append')]
        string $text,
    ): array {
        $actorId = $this->runner->actorId();
        $card = $this->context->card($cardId, $actorId);
        $current = rtrim($card->description());
        $description = $current === '' ? $text : $current . "\n\n" . $text;
        $this->runner->command(new UpdateCardContentCommand($cardId, $card->title(), $description, $actorId));

        return $this->cardResult($this->context->card($cardId, $actorId), $actorId, true);
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'set_due_date',
        description: 'Set the card due date (YYYY-MM-DD) or clear it with "". Any project member may. Convert relative dates ("by Friday") yourself and tell the user the date you set.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function setDueDate(
        #[Schema(description: 'Card id', format: 'uuid')]
        string $cardId,
        #[Schema(description: 'YYYY-MM-DD, or "" to clear')]
        string $dueDate,
    ): array {
        $actorId = $this->runner->actorId();
        $this->runner->command(new SetCardDueDateCommand($cardId, $dueDate === '' ? null : $dueDate, $actorId));

        return $this->cardResult($this->context->card($cardId, $actorId), $actorId, false);
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'add_label',
        description: 'Add one existing board label to the card; other labels stay. Any project member may. Label ids come from get_board; labels are created in the web UI.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function addLabel(
        #[Schema(description: 'Card id', format: 'uuid')]
        string $cardId,
        #[Schema(description: 'Label id from get_board', format: 'uuid')]
        string $labelId,
    ): array {
        return $this->changeLabels($cardId, $labelId, true);
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'remove_label',
        description: 'Remove one label from the card; other labels stay. Any project member may.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function removeLabel(
        #[Schema(description: 'Card id', format: 'uuid')]
        string $cardId,
        #[Schema(description: 'Label id', format: 'uuid')]
        string $labelId,
    ): array {
        return $this->changeLabels($cardId, $labelId, false);
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'assign',
        description: 'Add one assignee to the card; current assignees stay. Any project member may, on any card. Only project members can be assigned (user ids from get_board). If the user did not say whom, or a name matches several members, ask.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function assign(
        #[Schema(description: 'Card id', format: 'uuid')]
        string $cardId,
        #[Schema(description: 'Member user id from get_board', format: 'uuid')]
        string $userId,
    ): array {
        return $this->changeAssignees($cardId, $userId, true);
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'unassign',
        description: 'Remove one assignee from the card; other assignees stay. Any project member may.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function unassign(
        #[Schema(description: 'Card id', format: 'uuid')]
        string $cardId,
        #[Schema(description: 'User id', format: 'uuid')]
        string $userId,
    ): array {
        return $this->changeAssignees($cardId, $userId, false);
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'move_card',
        description: 'Move a card to another column of the same board — this changes its status ("close / finish" = move to the done column). Any project member may move any card. Lands at the end of the column unless a position is given (0 = top). If the target column is ambiguous, ask first and propose one.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function moveCard(
        #[Schema(description: 'Card id', format: 'uuid')]
        string $cardId,
        #[Schema(description: 'Target column id from get_board (same project)', format: 'uuid')]
        string $columnId,
        #[Schema(description: 'Zero-based position in the target column; omit for the end', minimum: 0)]
        ?int $position = null,
    ): array {
        $actorId = $this->runner->actorId();
        $card = $this->context->card($cardId, $actorId);
        try {
            // the move service clamps the index, so "very large" means "at the end"
            $this->runner->command(new MoveCardCommand($cardId, $columnId, $position ?? PHP_INT_MAX, $actorId));
        } catch (ToolCallException $e) {
            if ($e->getPrevious() instanceof ColumnNotFoundException) {
                throw new ToolCallException(
                    'Column not found on this card\'s board. ' . $this->context->columnsHint($this->context->projectId($card), $actorId),
                    previous: $e,
                );
            }
            throw $e;
        }

        return $this->cardResult($this->context->card($cardId, $actorId), $actorId, false);
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'add_comment',
        description: 'Add a markdown comment to a card; any project member may comment on any card. Comments cannot be edited or deleted through MCP: if you wrote the text yourself (not dictated by the user), show it and get confirmation first.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false),
    )]
    public function addComment(
        #[Schema(description: 'Card id', format: 'uuid')]
        string $cardId,
        #[Schema(description: 'Markdown text, up to 10000 characters')]
        string $body,
    ): array {
        /** @var Comment $comment */
        $comment = $this->runner->command(new CreateCommentCommand($cardId, $body, $this->runner->actorId()));

        return [
            'comment' => [
                'id' => (string)$comment->id(),
                'cardId' => (string)$comment->cardId(),
                'body' => $comment->body(),
                'createdAt' => $comment->createdAt()->format(\DateTimeInterface::ATOM),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function changeLabels(string $cardId, string $labelId, bool $add): array
    {
        $actorId = $this->runner->actorId();
        $card = $this->context->card($cardId, $actorId);
        $ids = array_map(static fn(Label $label): string => (string)$label->id(), array_values($card->labels()->toArray()));

        $has = in_array(strtolower($labelId), array_map('strtolower', $ids), true);
        if ($has === $add) {
            return $this->cardResult($card, $actorId, false) + ['note' => $add ? 'Label was already on the card.' : 'Label was not on the card.'];
        }
        $ids = $add
            ? [...$ids, $labelId]
            : array_values(array_filter($ids, static fn(string $id): bool => strcasecmp($id, $labelId) !== 0));

        try {
            $this->runner->command(new SetCardLabelsCommand($cardId, $ids, $actorId));
        } catch (ToolCallException $e) {
            if ($e->getPrevious() instanceof LabelNotFoundException) {
                throw new ToolCallException(
                    'Label not found on this board. ' . $this->context->labelsHint($this->context->projectId($card), $actorId),
                    previous: $e,
                );
            }
            throw $e;
        }

        return $this->cardResult($this->context->card($cardId, $actorId), $actorId, false);
    }

    /**
     * @return array<string, mixed>
     */
    private function changeAssignees(string $cardId, string $userId, bool $add): array
    {
        $actorId = $this->runner->actorId();
        $card = $this->context->card($cardId, $actorId);
        $ids = array_map(static fn(User $user): string => (string)$user->id(), array_values($card->assignees()->toArray()));

        $has = in_array(strtolower($userId), array_map('strtolower', $ids), true);
        if ($has === $add) {
            return $this->cardResult($card, $actorId, false) + ['note' => $add ? 'User was already assigned.' : 'User was not assigned.'];
        }
        $ids = $add
            ? [...$ids, $userId]
            : array_values(array_filter($ids, static fn(string $id): bool => strcasecmp($id, $userId) !== 0));

        try {
            $this->runner->command(new SetCardAssigneesCommand($cardId, $ids, $actorId));
        } catch (ToolCallException $e) {
            if ($e->getPrevious() instanceof ProjectMemberNotFoundException) {
                throw new ToolCallException(
                    'This user is not a member of the project. ' . $this->context->membersHint($this->context->projectId($card), $actorId),
                    previous: $e,
                );
            }
            throw $e;
        }

        return $this->cardResult($this->context->card($cardId, $actorId), $actorId, false);
    }

    /**
     * @return array<string, mixed>
     */
    private function cardResult(Card $card, string $actorId, bool $withDescription): array
    {
        return ['card' => McpPresenter::card($card, $withDescription, $this->context->canEdit($card, $actorId))];
    }
}

<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Tool;

use App\Board\Application\Message\Query\GetBoardQuery;
use App\Board\Application\Message\Query\GetCardQuery;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\CardActivity;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Entity\Comment;
use App\Board\Domain\Entity\Label;
use App\Mcp\Application\Message\Query\ListRecentActivityQuery;
use App\Mcp\Domain\ReadModel\RecentActivity;
use App\Project\Application\Message\Query\GetProjectQuery;
use App\Project\Application\Message\Query\ListProjectsQuery;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Enum\MemberRole;
use App\User\Domain\Repository\UserRepository;
use App\UserEvent\Application\Dto\UserEventPage;
use App\UserEvent\Application\Message\Query\ListUserEventsQuery;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ReadTools
{
    /** @var array<string, string> */
    private array $userNames = [];

    public function __construct(
        private McpToolRunner $runner,
        private UserRepository $userRepository,
        private CardContext $context,
        #[Autowire('%mcp.version%')]
        private string $version,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'mcp_version',
        description: 'Version of this kanban MCP server: a plain build number (2, 3, 4…) that grows with every release. Use it to tell which build is deployed.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function mcpVersion(): array
    {
        return ['server' => 'kanban', 'version' => $this->version];
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'list_projects',
        description: 'List the kanban projects the user can access (archived projects excluded), with the user\'s role and a link to each board.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function listProjects(): array
    {
        /** @var list<array{project: Project, role: MemberRole}> $rows */
        $rows = $this->runner->query(new ListProjectsQuery($this->runner->actorId()));

        $projects = [];
        foreach ($rows as $row) {
            if ($row['project']->isArchived()) {
                continue;
            }
            $id = (string)$row['project']->id();
            $projects[] = [
                'id' => $id,
                'name' => $row['project']->name(),
                'role' => $row['role']->value,
                'url' => $this->runner->projectUrl($id),
            ];
        }

        return ['projects' => $projects];
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'get_board',
        description: 'Get a project board: members, labels, and columns (the column is the card status) with their cards in order. Each card has canEdit: whether you may change its title and description. Card descriptions are omitted — use get_card.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function getBoard(
        #[Schema(description: 'Project id from list_projects', format: 'uuid')]
        string $projectId,
    ): array {
        $actorId = $this->runner->actorId();

        /** @var array{project: Project, role: MemberRole, members: list<array{userId: UuidInterface, role: MemberRole, displayName: string}>} $project */
        $project = $this->runner->query(new GetProjectQuery($projectId, $actorId));
        /** @var array{columns: list<Column>, cards: list<Card>, labels: list<Label>} $board */
        $board = $this->runner->query(new GetBoardQuery($projectId, $actorId));

        $columns = [];
        foreach ($board['columns'] as $column) {
            $cards = array_values(array_filter(
                $board['cards'],
                static fn(Card $card): bool => $card->columnId()->equals($column->id()),
            ));
            usort($cards, static fn(Card $a, Card $b): int => $a->position() <=> $b->position());

            $columns[] = [
                'id' => (string)$column->id(),
                'name' => $column->name(),
                'position' => $column->position(),
                'cards' => array_map(
                    fn(Card $card): array => McpPresenter::card($card, false, $this->context->canEdit($card, $actorId)),
                    $cards,
                ),
            ];
        }
        usort($columns, static fn(array $a, array $b): int => $a['position'] <=> $b['position']);

        return [
            'project' => [
                'id' => (string)$project['project']->id(),
                'name' => $project['project']->name(),
                'myRole' => $project['role']->value,
                'url' => $this->runner->projectUrl((string)$project['project']->id()),
            ],
            'members' => array_map(static fn(array $member): array => [
                'userId' => (string)$member['userId'],
                'name' => $member['displayName'],
                'role' => $member['role']->value,
            ], $project['members']),
            'labels' => array_map(static fn(Label $label): array => [
                'id' => (string)$label->id(),
                'name' => $label->name(),
                'color' => $label->color(),
            ], $board['labels']),
            'columns' => $columns,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'get_card',
        description: 'Get one card in full: description, labels, due date, assignees, comments and its activity history.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function getCard(
        #[Schema(description: 'Card id', format: 'uuid')]
        string $cardId,
    ): array {
        $actorId = $this->runner->actorId();
        /** @var array{card: Card, comments: list<Comment>, activities: list<CardActivity>} $result */
        $result = $this->runner->query(new GetCardQuery($cardId, $actorId));

        return [
            'card' => McpPresenter::card($result['card'], true, $this->context->canEdit($result['card'], $actorId)),
            'comments' => array_map(fn(Comment $comment): array => [
                'id' => (string)$comment->id(),
                'author' => $this->userName($comment->authorId()),
                'authorId' => (string)$comment->authorId(),
                'body' => $comment->body(),
                'createdAt' => $comment->createdAt()->format(\DateTimeInterface::ATOM),
            ], $result['comments']),
            'activity' => array_map(fn(CardActivity $activity): array => [
                'type' => $activity->type(),
                'actor' => $activity->actorId() !== null ? $this->userName($activity->actorId()) : null,
                'payload' => $activity->payload(),
                'at' => $activity->createdAt()->format(\DateTimeInterface::ATOM),
            ], $result['activities']),
        ];
    }

    /**
     * The user's own notifications (UserEvent module): one indexed table, names stored at
     * write time, cursor pages — no joins over the working tables. Never marks anything
     * seen: the user still sees them unread on the site.
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'my_notifications',
        description: 'What\'s new for the user: their notifications, newest first — assigned to / removed from a card, new comments on cards they created or are assigned to, role changes, project archived or restored. Read-only; does not mark anything as seen. Pass nextCursor to get older ones.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function myNotifications(
        #[Schema(description: 'nextCursor from the previous call; omit for the newest page')]
        ?string $cursor = null,
        #[Schema(description: 'Page size, 1–50', minimum: 1, maximum: 50)]
        int $limit = 20,
    ): array {
        /** @var UserEventPage $page */
        $page = $this->runner->query(new ListUserEventsQuery($cursor, $limit, $this->runner->actorId()));

        return [
            'notifications' => array_map(fn(array $item): array => [
                'at' => $item['occurredOn'],
                'type' => $item['type'],
                'unread' => $item['seenAt'] === null,
                'actor' => $item['actorName'],
                'project' => $item['projectName'],
                'projectId' => $item['projectId'],
                'card' => $item['cardTitle'],
                'cardId' => $item['cardId'],
                'role' => $item['role'],
                'url' => is_string($item['projectId']) ? $this->runner->projectUrl($item['projectId']) : null,
            ], $page->items),
            'nextCursor' => $page->nextCursor,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'whats_new',
        description: 'What\'s new: card activity (created, edited, moved, assigned, labelled, archived, commented…) across the user\'s projects since a moment, newest first. Defaults to the last 24 hours; at most 200 entries.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function whatsNew(
        #[Schema(description: 'ISO 8601 date-time to look back to, e.g. 2026-11-17T09:00:00+00:00. Omit for the last 24 hours.')]
        ?string $since = null,
    ): array {
        /** @var list<RecentActivity> $entries */
        $entries = $this->runner->query(new ListRecentActivityQuery($this->runner->actorId(), $since ?? ''));

        return [
            'count' => count($entries),
            'activity' => array_map(fn(RecentActivity $entry): array => [
                'at' => $entry->createdAt->format(\DateTimeInterface::ATOM),
                'project' => $entry->projectName,
                'projectId' => (string)$entry->projectId,
                'card' => $entry->cardTitle,
                'cardId' => (string)$entry->cardId,
                'actor' => $entry->actorName,
                'type' => $entry->type,
                'details' => $entry->payload,
                'url' => $this->runner->projectUrl((string)$entry->projectId),
            ], $entries),
        ];
    }

    private function userName(UuidInterface $userId): string
    {
        $key = $userId->toString();
        if (!isset($this->userNames[$key])) {
            $this->userNames[$key] = $this->userRepository->findById(Uuid::fromString($key))?->displayName() ?? 'unknown';
        }

        return $this->userNames[$key];
    }
}

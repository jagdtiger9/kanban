<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Tool;

use App\Board\Application\Message\Query\GetBoardQuery;
use App\Board\Application\Message\Query\GetCardQuery;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Entity\Label;
use App\Project\Application\Message\Query\GetProjectQuery;
use App\Project\Domain\Enum\MemberRole;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Card facts the tools need beyond the plain queries: whether the actor may edit the card
 * (same rule as CardGuard::requireCardEditable) and the board's columns, labels and members
 * to suggest in error messages.
 */
final class CardContext
{
    public function __construct(
        private McpToolRunner $runner,
        private CardGuard $cardGuard,
    ) {
    }

    public function card(string $cardId, string $actorId): Card
    {
        /** @var array{card: Card} $result */
        $result = $this->runner->query(new GetCardQuery($cardId, $actorId));

        return $result['card'];
    }

    /** May the actor change title and description (creator, project admin, global admin)? */
    public function canEdit(Card $card, string $actorId): bool
    {
        $actor = Uuid::fromString($actorId);

        return $card->creatorId()->equals($actor)
            || $this->cardGuard->effectiveRoleOnCard($card, $actor) === MemberRole::Admin;
    }

    public function projectId(Card $card): string
    {
        return (string)$this->cardGuard->resolveBoard($card)->projectId();
    }

    public function columnsHint(string $projectId, string $actorId): string
    {
        /** @var array{columns: list<Column>} $board */
        $board = $this->runner->query(new GetBoardQuery($projectId, $actorId));

        return 'Columns on this board: ' . self::listing(array_map(
            static fn(Column $column): array => [$column->name(), $column->id()],
            $board['columns'],
        ));
    }

    public function labelsHint(string $projectId, string $actorId): string
    {
        /** @var array{labels: list<Label>} $board */
        $board = $this->runner->query(new GetBoardQuery($projectId, $actorId));

        return 'Labels on this board: ' . self::listing(array_map(
            static fn(Label $label): array => [$label->name(), $label->id()],
            $board['labels'],
        )) . ' New labels are created in the web UI.';
    }

    public function membersHint(string $projectId, string $actorId): string
    {
        /** @var array{members: list<array{userId: UuidInterface, displayName: string}>} $project */
        $project = $this->runner->query(new GetProjectQuery($projectId, $actorId));

        return 'Project members: ' . self::listing(array_map(
            static fn(array $member): array => [$member['displayName'], $member['userId']],
            $project['members'],
        ));
    }

    /**
     * @param list<array{string, UuidInterface}> $items
     */
    private static function listing(array $items): string
    {
        if ($items === []) {
            return 'none.';
        }

        return implode(', ', array_map(
            static fn(array $item): string => sprintf('"%s" (%s)', $item[0], $item[1]->toString()),
            $items,
        )) . '.';
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Integration\Mcp\Infrastructure;

use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\CardActivity;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\CardActivityRepository;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use App\Mcp\Domain\Repository\RecentActivityReader;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\Project\Domain\Repository\ProjectRepository;
use App\Tests\Integration\DatabaseTestCase;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use Ramsey\Uuid\Uuid;

final class DoctrineRecentActivityReaderTest extends DatabaseTestCase
{
    private RecentActivityReader $reader;
    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reader = $this->repo(RecentActivityReader::class);

        $this->member = User::create(Uuid::uuid4(), 'member@mcp.test', 'hash', 'Member');
        $this->member->confirmAccount();
        $this->repo(UserRepository::class)->save($this->member);
    }

    /**
     * @return array{Project, Card}
     */
    private function projectWithCard(string $name, bool $withMember): array
    {
        $project = Project::create(Uuid::uuid4(), $name);
        $this->repo(ProjectRepository::class)->save($project);
        $board = Board::create(Uuid::uuid4(), $project->id(), $name);
        $this->repo(BoardRepository::class)->save($board);
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $this->repo(ColumnRepository::class)->save($column);
        $card = Card::create(Uuid::uuid4(), $column->id(), $this->member->id(), $name . ' card', '', 0);
        $this->repo(CardRepository::class)->save($card);
        if ($withMember) {
            $this->repo(ProjectMemberRepository::class)
                ->save(ProjectMember::create(Uuid::uuid4(), $project->id(), $this->member->id(), MemberRole::Member));
        }

        $this->repo(CardActivityRepository::class)->save(
            CardActivity::create(Uuid::uuid4(), $card->id(), $this->member->id(), 'card_created', ['title' => $card->title()]),
        );

        return [$project, $card];
    }

    public function testMemberSeesOnlyOwnNonArchivedProjectsWithNames(): void
    {
        [$own, $card] = $this->projectWithCard('Own', true);
        $this->projectWithCard('Foreign', false);
        [$archived] = $this->projectWithCard('Archived', true);
        $archived->archive($this->member->id());
        $this->repo(ProjectRepository::class)->save($archived);
        $this->em()->clear();

        $result = $this->reader->findSince(new \DateTimeImmutable('-1 hour'), $this->member->id(), 50);

        self::assertCount(1, $result);
        self::assertTrue($own->id()->equals($result[0]->projectId));
        self::assertSame('Own', $result[0]->projectName);
        self::assertTrue($card->id()->equals($result[0]->cardId));
        self::assertSame('Own card', $result[0]->cardTitle);
        self::assertSame('Member', $result[0]->actorName);
        self::assertSame('card_created', $result[0]->type);
        self::assertSame(['title' => 'Own card'], $result[0]->payload);
    }

    public function testWithoutMemberFilterSeesAllNonArchivedProjects(): void
    {
        $this->projectWithCard('Own', true);
        $this->projectWithCard('Foreign', false);
        $this->em()->clear();

        $result = $this->reader->findSince(new \DateTimeImmutable('-1 hour'), null, 50);

        self::assertCount(2, $result);
    }

    public function testSinceAndLimitAreApplied(): void
    {
        $this->projectWithCard('One', true);
        $this->projectWithCard('Two', true);
        $this->em()->clear();

        self::assertCount(0, $this->reader->findSince(new \DateTimeImmutable('+1 hour'), $this->member->id(), 50));
        self::assertCount(1, $this->reader->findSince(new \DateTimeImmutable('-1 hour'), $this->member->id(), 1));
    }
}

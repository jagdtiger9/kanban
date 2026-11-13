<?php

declare(strict_types=1);

namespace App\Tests\Integration\Security;

use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Entity\Comment;
use App\Board\Domain\Entity\Label;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use App\Board\Domain\Repository\CommentRepository;
use App\Board\Domain\Repository\LabelRepository;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\Project\Domain\Repository\ProjectRepository;
use App\Shared\Infrastructure\Security\SecurityUser;
use App\Tests\Integration\DatabaseTestCase;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

/**
 * Route × actor matrix over the HTTP edge: every route must answer the agreed
 * status for anonymous, non-member, project member, project admin and global
 * admin actors. One request per test on a freshly reset database.
 */
final class AclMatrixTest extends DatabaseTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        // createClient() must run before the kernel is booted any other way.
        $this->client = self::createClient();
        $this->prepareDatabase();
    }

    #[DataProvider('aclMatrix')]
    public function testAclMatrix(string $route, string $actor, int $expectedStatus): void
    {
        $fx = $this->seedFixture();
        [$method, $path, $body] = $this->routes($fx)[$route];

        if ($actor !== 'anon') {
            $this->client->loginUser(SecurityUser::fromUser($fx['users'][$actor]));
        }

        $this->client->request(
            $method,
            $path,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR),
        );

        self::assertSame(
            $expectedStatus,
            $this->client->getResponse()->getStatusCode(),
            sprintf('%s as %s', $route, $actor),
        );
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function aclMatrix(): iterable
    {
        $matrix = [
            // Authenticated users only
            'projects.list' => self::userRoute(200),
            'project.create' => self::userRoute(201),
            'users.search' => self::userRoute(200),
            'auth.me' => self::userRoute(200),
            'auth.password' => self::userRoute(200),

            // Public
            'auth.register' => ['anon' => 201],
            'auth.confirm' => ['anon' => 200],
            'auth.login' => ['anon' => 200],

            // Project members
            'project.get' => self::memberRoute(200),
            'board.get' => self::memberRoute(200),
            'column.create' => self::memberRoute(201),
            'column.rename' => self::memberRoute(200),
            'column.reorder' => self::memberRoute(204),
            'card.create' => self::memberRoute(201),
            'card.get' => self::memberRoute(200),
            'card.move' => self::memberRoute(204),
            'card.labels' => self::memberRoute(204),
            'card.assignees' => self::memberRoute(204),
            'card.dueDate' => self::memberRoute(204),
            'comment.create' => self::memberRoute(201),
            'label.create' => self::memberRoute(201),
            'label.update' => self::memberRoute(200),

            // Project admins
            'project.rename' => self::adminRoute(200),
            'project.archive' => self::adminRoute(200),
            'project.restore' => self::adminRoute(200),
            'project.memberAdd' => self::adminRoute(201),
            'project.memberRole' => self::adminRoute(200),
            'project.memberRemove' => self::adminRoute(204),
            'column.delete' => self::adminRoute(204),
            'label.delete' => self::adminRoute(204),

            // Card editor: creator, project admin, global admin
            'card.update' => ['anon' => 401, 'outsider' => 403, 'member' => 200, 'other' => 403, 'admin' => 200, 'global' => 200],
            'card.archive' => ['anon' => 401, 'outsider' => 403, 'member' => 204, 'other' => 403, 'admin' => 204, 'global' => 204],
            'card.restore' => ['anon' => 401, 'outsider' => 403, 'member' => 204, 'other' => 403, 'admin' => 204, 'global' => 204],

            // Comment editor: author only, admins do not override
            'comment.update' => ['anon' => 401, 'outsider' => 403, 'member' => 200, 'other' => 403, 'admin' => 403, 'global' => 403],

            // Comment manager: author or project/global admin
            'comment.delete' => ['anon' => 401, 'outsider' => 403, 'member' => 204, 'other' => 403, 'admin' => 204, 'global' => 204],

            // Global admins only
            'project.delete' => ['anon' => 401, 'outsider' => 403, 'member' => 403, 'other' => 403, 'admin' => 403, 'global' => 204],
            'users.list' => ['anon' => 401, 'outsider' => 403, 'member' => 403, 'other' => 403, 'admin' => 403, 'global' => 200],
            'user.confirm' => ['anon' => 401, 'outsider' => 403, 'member' => 403, 'other' => 403, 'admin' => 403, 'global' => 200],
            'user.promote' => ['anon' => 401, 'outsider' => 403, 'member' => 403, 'other' => 403, 'admin' => 403, 'global' => 200],
            'user.demote' => ['anon' => 401, 'outsider' => 403, 'member' => 403, 'other' => 403, 'admin' => 403, 'global' => 200],
        ];

        foreach ($matrix as $route => $byActor) {
            foreach ($byActor as $actor => $expectedStatus) {
                yield sprintf('%s as %s', $route, $actor) => [$route, $actor, $expectedStatus];
            }
        }
    }

    /**
     * @return array<string, int>
     */
    private static function userRoute(int $success): array
    {
        return ['anon' => 401, 'outsider' => $success, 'member' => $success, 'other' => $success, 'admin' => $success, 'global' => $success];
    }

    /**
     * @return array<string, int>
     */
    private static function memberRoute(int $success): array
    {
        return ['anon' => 401, 'outsider' => 403, 'member' => $success, 'other' => $success, 'admin' => $success, 'global' => $success];
    }

    /**
     * @return array<string, int>
     */
    private static function adminRoute(int $success): array
    {
        return ['anon' => 401, 'outsider' => 403, 'member' => 403, 'other' => 403, 'admin' => $success, 'global' => $success];
    }

    /**
     * Actors: member authored the seeded card and comment; other is a plain
     * member; admin holds the project admin role; global is a global admin
     * with no project membership; admin2 is a second global admin used as the
     * demote target so the acting global user stays untouched; outsider has no
     * access at all; candidate is unconfirmed with a pending token.
     *
     * @return array{users: array<string, User>, projectId: string, columnId: string, column2Id: string, labelId: string, cardId: string, commentId: string, confirmationToken: string}
     */
    private function seedFixture(): array
    {
        $usersRepo = $this->repo(UserRepository::class);
        $confirmationToken = 'candidate-confirmation-token';
        $passwordHash = self::getContainer()
            ->get(PasswordHasherFactoryInterface::class)
            ->getPasswordHasher(User::class)
            ->hash('secret123');
        $users = [];
        foreach (['outsider', 'member', 'other', 'admin', 'global', 'admin2', 'candidate'] as $name) {
            $user = User::create(Uuid::uuid4(), sprintf('%s@acl.test', $name), $passwordHash, ucfirst($name));
            if ($name === 'global' || $name === 'admin2') {
                $user->promoteToGlobalAdmin();
            }
            if ($name === 'candidate') {
                $user->beginEmailConfirmation(hash('sha256', $confirmationToken), new \DateTimeImmutable('+24 hours'));
            } else {
                $user->confirmAccount();
            }
            $usersRepo->save($user);
            $users[$name] = $user;
        }

        $project = Project::create(Uuid::uuid4(), 'ACL project');
        $this->repo(ProjectRepository::class)->save($project);

        $board = Board::create(Uuid::uuid4(), $project->id(), 'ACL board');
        $this->repo(BoardRepository::class)->save($board);

        $todo = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $done = Column::create(Uuid::uuid4(), $board->id(), 'Done', 1);
        $columnsRepo = $this->repo(ColumnRepository::class);
        $columnsRepo->save($todo);
        $columnsRepo->save($done);

        $label = Label::create(Uuid::uuid4(), $board->id(), 'Bug', '#ff0000');
        $this->repo(LabelRepository::class)->save($label);

        $card = Card::create(Uuid::uuid4(), $todo->id(), $users['member']->id(), 'Seed card', 'Seed description', 0);
        $this->repo(CardRepository::class)->save($card);

        $comment = Comment::create(Uuid::uuid4(), $card->id(), $users['member']->id(), 'Seed comment');
        $this->repo(CommentRepository::class)->save($comment);

        $membersRepo = $this->repo(ProjectMemberRepository::class);
        $membersRepo->save(ProjectMember::create(Uuid::uuid4(), $project->id(), $users['member']->id(), MemberRole::Member));
        $membersRepo->save(ProjectMember::create(Uuid::uuid4(), $project->id(), $users['other']->id(), MemberRole::Member));
        $membersRepo->save(ProjectMember::create(Uuid::uuid4(), $project->id(), $users['admin']->id(), MemberRole::Admin));

        return [
            'users' => $users,
            'projectId' => (string) $project->id(),
            'columnId' => (string) $todo->id(),
            'column2Id' => (string) $done->id(),
            'labelId' => (string) $label->id(),
            'cardId' => (string) $card->id(),
            'commentId' => (string) $comment->id(),
            'confirmationToken' => $confirmationToken,
        ];
    }

    /**
     * @param array{users: array<string, User>, projectId: string, columnId: string, column2Id: string, labelId: string, cardId: string, commentId: string, confirmationToken: string} $fx
     *
     * @return array<string, array{string, string, array<string, mixed>|null}>
     */
    private function routes(array $fx): array
    {
        $project = $fx['projectId'];
        $column = $fx['columnId'];
        $otherColumn = $fx['column2Id'];
        $card = $fx['cardId'];
        $comment = $fx['commentId'];
        $label = $fx['labelId'];
        $memberId = (string) $fx['users']['member']->id();
        $otherId = (string) $fx['users']['other']->id();
        $candidateId = (string) $fx['users']['candidate']->id();
        $admin2Id = (string) $fx['users']['admin2']->id();

        return [
            'projects.list' => ['GET', '/api/projects', null],
            'project.create' => ['POST', '/api/projects', ['name' => 'Created project']],
            'users.search' => ['GET', '/api/users/search?term=acl', null],
            'auth.me' => ['GET', '/api/auth/me', null],
            'auth.password' => ['POST', '/api/auth/password', ['currentPassword' => 'secret123', 'newPassword' => 'newsecret123', 'newPasswordConfirmation' => 'newsecret123']],
            'auth.register' => ['POST', '/api/auth/register', ['email' => 'newcomer@acl.test', 'password' => 'secret123', 'passwordConfirmation' => 'secret123', 'displayName' => 'Newcomer']],
            'auth.confirm' => ['POST', '/api/auth/confirm', ['token' => $fx['confirmationToken']]],
            'auth.login' => ['POST', '/api/auth/login', ['email' => 'member@acl.test', 'password' => 'secret123']],

            'project.get' => ['GET', "/api/projects/$project", null],
            'board.get' => ['GET', "/api/projects/$project/board", null],
            'column.create' => ['POST', "/api/projects/$project/columns", ['name' => 'New column']],
            'column.rename' => ['PUT', "/api/columns/$column", ['name' => 'Renamed column']],
            'column.reorder' => ['PUT', "/api/columns/$column/reorder", ['targetIndex' => 1]],
            'card.create' => ['POST', "/api/columns/$column/cards", ['title' => 'Created card']],
            'card.get' => ['GET', "/api/cards/$card", null],
            'card.move' => ['PUT', "/api/cards/$card/move", ['targetColumnId' => $otherColumn, 'targetIndex' => 0]],
            'card.labels' => ['PUT', "/api/cards/$card/labels", ['labelIds' => [$label]]],
            'card.assignees' => ['PUT', "/api/cards/$card/assignees", ['userIds' => [$memberId]]],
            'card.dueDate' => ['PUT', "/api/cards/$card/due-date", ['dueDate' => '2026-12-01']],
            'comment.create' => ['POST', "/api/cards/$card/comments", ['body' => 'Created comment']],
            'label.create' => ['POST', "/api/projects/$project/labels", ['name' => 'New label', 'color' => '#00ff00']],
            'label.update' => ['PUT', "/api/labels/$label", ['name' => 'Renamed label', 'color' => '#0000ff']],

            'project.rename' => ['PUT', "/api/projects/$project", ['name' => 'Renamed project']],
            'project.archive' => ['POST', "/api/projects/$project/archive", null],
            'project.restore' => ['POST', "/api/projects/$project/restore", null],
            'project.memberAdd' => ['POST', "/api/projects/$project/members", ['userId' => $candidateId, 'role' => 'member']],
            'project.memberRole' => ['PUT', "/api/projects/$project/members/$otherId", ['role' => 'admin']],
            'project.memberRemove' => ['DELETE', "/api/projects/$project/members/$otherId", null],
            'column.delete' => ['DELETE', "/api/columns/$otherColumn", null],
            'label.delete' => ['DELETE', "/api/labels/$label", null],

            'card.update' => ['PUT', "/api/cards/$card", ['title' => 'Edited card', 'description' => '']],
            'card.archive' => ['DELETE', "/api/cards/$card", null],
            'card.restore' => ['POST', "/api/cards/$card/restore", null],

            'comment.update' => ['PUT', "/api/comments/$comment", ['body' => 'Edited comment']],
            'comment.delete' => ['DELETE', "/api/comments/$comment", null],

            'project.delete' => ['DELETE', "/api/projects/$project", null],

            'users.list' => ['GET', '/api/users', null],
            'user.confirm' => ['POST', "/api/users/$candidateId/confirm", null],
            'user.promote' => ['POST', "/api/users/$otherId/promote", null],
            'user.demote' => ['POST', "/api/users/$admin2Id/demote", null],
        ];
    }
}

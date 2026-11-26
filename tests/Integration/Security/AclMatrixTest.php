<?php

declare(strict_types=1);

namespace App\Tests\Integration\Security;

use App\Board\Domain\Entity\Attachment;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Entity\Comment;
use App\Board\Domain\Entity\Label;
use App\Board\Domain\Repository\AttachmentRepository;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use App\Board\Domain\Repository\CommentRepository;
use App\Board\Domain\Repository\LabelRepository;
use App\Board\Domain\ValueObject\RecurrenceRule;
use App\News\Domain\Entity\NewsPost;
use App\News\Domain\Repository\NewsPostRepository;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\Project\Domain\Repository\ProjectRepository;
use App\Shared\Application\Storage\FileStorage;
use App\Shared\Infrastructure\Security\SecurityUser;
use App\Tests\Integration\DatabaseTestCase;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Route × actor matrix over the HTTP edge: every route must answer the agreed
 * status for anonymous, non-member, project member, project admin, global admin
 * and news maker actors. One request per test on a freshly reset database.
 */
final class AclMatrixTest extends DatabaseTestCase
{
    private KernelBrowser $client;

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        // createClient() must run before the kernel is booted any other way.
        $this->client = self::createClient();
        $this->prepareDatabase();
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    #[DataProvider('aclMatrix')]
    public function testAclMatrix(string $route, string $actor, int $expectedStatus): void
    {
        $fx = $this->seedFixture();
        /** @var array{0: string, 1: string, 2: array<string, mixed>|null, 3?: array<string, UploadedFile>} $spec */
        $spec = $this->routes($fx)[$route];
        [$method, $path, $body] = $spec;
        $files = $spec[3] ?? [];

        if ($actor !== 'anon') {
            $this->client->loginUser(SecurityUser::fromUser($fx['users'][$actor]));
        }

        if ($files === []) {
            $this->client->request(
                $method,
                $path,
                [],
                [],
                ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
                $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR),
            );
        } else {
            $this->client->request(
                $method,
                $path,
                $body ?? [],
                $files,
                ['CONTENT_TYPE' => 'multipart/form-data', 'HTTP_ACCEPT' => 'application/json'],
            );
        }

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
        foreach (self::matrix() as $route => $byActor) {
            foreach ($byActor as $actor => $expectedStatus) {
                yield sprintf('%s as %s', $route, $actor) => [$route, $actor, $expectedStatus];
            }
        }
    }

    public function testEveryApiRouteIsCovered(): void
    {
        /** @var RouterInterface $router */
        $router = self::getContainer()->get('router');
        $apiRoutes = array_values(
            array_filter(
                array_keys($router->getRouteCollection()->all()),
                static fn(string $name): bool => str_starts_with($name, 'api_'),
            )
        );
        $covered = array_keys(self::matrix());

        sort($apiRoutes);
        sort($covered);

        self::assertSame($apiRoutes, $covered);
    }

    /**
     * @return array<string, array<string, int>>
     */
    private static function matrix(): array
    {
        return [
            // Authenticated users only
            'api_projects_list' => self::userRoute(200),
            'api_project_create' => self::userRoute(201),
            'api_user_search' => self::userRoute(200),
            'api_auth_me' => self::userRoute(200),
            'api_auth_password' => self::userRoute(200),
            'api_mcp_info' => self::userRoute(200),
            'api_mcp_token' => self::userRoute(201),
            'api_user_avatar_update' => self::userRoute(200),
            'api_user_avatar_delete' => self::userRoute(204),
            'api_user_avatar_get' => self::userRoute(200),
            'api_user_color_update' => self::userRoute(200),
            'api_call_token' => self::userRoute(200),
            'api_call_check' => self::userRoute(200),
            'api_call_rooms' => self::userRoute(200),
            'api_user_realtime_token' => self::userRoute(200),
            'api_user_events_list' => self::userRoute(200),
            'api_user_events_unread_count' => self::userRoute(200),
            'api_user_events_seen' => self::userRoute(204),

            // Public
            'api_ping' => self::publicRoute(200),
            'api_auth_register' => ['anon' => 201],
            'api_auth_confirm' => ['anon' => 200],
            'api_auth_login' => ['anon' => 200],

            // Project members
            'api_project_get' => self::memberRoute(200),
            'api_board_get' => self::memberRoute(200),
            'api_realtime_token' => self::memberRoute(200),
            'api_call_join' => self::memberRoute(200),
            'api_call_leave' => self::memberRoute(204),
            'api_call_heartbeat' => self::memberRoute(200),
            // nobody is in the seeded room, so relaying and inviting are refused for members
            'api_call_signal' => self::memberRoute(409),
            'api_call_invite' => self::memberRoute(409),
            'api_call_decline' => self::memberRoute(204),
            'api_card_create' => self::memberRoute(201),
            'api_card_get' => self::memberRoute(200),
            'api_card_seen' => self::memberRoute(204),
            'api_card_move' => self::memberRoute(204),
            'api_card_labels' => self::memberRoute(204),
            'api_card_assignees' => self::memberRoute(204),
            'api_card_executor' => self::memberRoute(204),
            'api_card_due_date' => self::memberRoute(204),
            'api_card_linked_card' => self::memberRoute(204),
            'api_card_recurrence' => self::memberRoute(204),
            'api_card_complete' => self::memberRoute(204),
            'api_card_uncomplete' => self::memberRoute(204),
            'api_card_comment_create' => self::memberRoute(201),
            'api_card_attachment_create' => self::memberRoute(201),
            'api_card_attachment_delete' => self::memberRoute(204),
            'api_attachment_download' => self::memberRoute(200),
            'api_attachment_thumbnail' => self::memberRoute(200),
            'api_label_create' => self::memberRoute(201),
            'api_label_update' => self::memberRoute(200),
            // Favourites are stored on the actor's own membership row; a global
            // admin without a membership has nowhere to store the flag.
            'api_project_set_favourite' => ['anon' => 401, 'outsider' => 403, 'member' => 204, 'other' => 204, 'admin' => 204, 'global' => 403, 'news' => 403],
            // Presence is stored on the membership row; a global admin without
            // one is accepted but nothing is written.
            'api_project_presence_heartbeat' => self::memberRoute(204),

            // Project admins
            'api_project_rename' => self::adminRoute(200),
            'api_project_archive' => self::adminRoute(200),
            'api_project_restore' => self::adminRoute(200),
            'api_project_member_add' => self::adminRoute(201),
            'api_project_member_change_role' => self::adminRoute(200),
            'api_project_member_remove' => self::adminRoute(204),
            'api_column_create' => self::adminRoute(201),
            'api_column_rename' => self::adminRoute(200),
            'api_column_reorder' => self::adminRoute(204),
            'api_column_delete' => self::adminRoute(204),
            'api_label_delete' => self::adminRoute(204),

            // Card editor: creator, project admin, global admin
            'api_card_update' => ['anon' => 401, 'outsider' => 403, 'member' => 200, 'other' => 403, 'admin' => 200, 'global' => 200],
            'api_card_archive' => ['anon' => 401, 'outsider' => 403, 'member' => 204, 'other' => 403, 'admin' => 204, 'global' => 204],
            'api_card_restore' => ['anon' => 401, 'outsider' => 403, 'member' => 204, 'other' => 403, 'admin' => 204, 'global' => 204],

            // Comment editor: author only, admins do not override
            'api_card_comment_update' => ['anon' => 401, 'outsider' => 403, 'member' => 200, 'other' => 403, 'admin' => 403, 'global' => 403],

            // Comment manager: author or project/global admin
            'api_card_comment_delete' => ['anon' => 401, 'outsider' => 403, 'member' => 204, 'other' => 403, 'admin' => 204, 'global' => 204],
            'api_comment_attachment_delete' => ['anon' => 401, 'outsider' => 403, 'member' => 204, 'other' => 403, 'admin' => 204, 'global' => 204],

            // News feed: read for every authenticated user; create for global
            // admins and news makers; edit/delete for the author or a global admin.
            'api_news_list' => self::userRoute(200),
            'api_news_create' => ['anon' => 401, 'outsider' => 403, 'member' => 403, 'other' => 403, 'admin' => 403, 'global' => 201, 'news' => 201],
            'api_news_update' => ['anon' => 401, 'outsider' => 403, 'member' => 200, 'other' => 403, 'admin' => 403, 'global' => 200, 'news' => 403],
            'api_news_delete' => ['anon' => 401, 'outsider' => 403, 'member' => 204, 'other' => 403, 'admin' => 403, 'global' => 204, 'news' => 403],

            // Global admins only
            'api_project_delete' => ['anon' => 401, 'outsider' => 403, 'member' => 403, 'other' => 403, 'admin' => 403, 'global' => 204],
            'api_users_list' => ['anon' => 401, 'outsider' => 403, 'member' => 403, 'other' => 403, 'admin' => 403, 'global' => 200],
            'api_user_confirm' => ['anon' => 401, 'outsider' => 403, 'member' => 403, 'other' => 403, 'admin' => 403, 'global' => 200],
            'api_user_promote_news_maker' => ['anon' => 401, 'outsider' => 403, 'member' => 403, 'other' => 403, 'admin' => 403, 'global' => 200, 'news' => 403],
            'api_user_demote_news_maker' => ['anon' => 401, 'outsider' => 403, 'member' => 403, 'other' => 403, 'admin' => 403, 'global' => 200, 'news' => 403],
        ];
    }

    /**
     * @return array<string, int>
     */
    private static function publicRoute(int $success): array
    {
        return ['anon' => $success, 'outsider' => $success, 'member' => $success, 'other' => $success, 'admin' => $success, 'global' => $success, 'news' => $success];
    }

    /**
     * @return array<string, int>
     */
    private static function userRoute(int $success): array
    {
        return ['anon' => 401, 'outsider' => $success, 'member' => $success, 'other' => $success, 'admin' => $success, 'global' => $success, 'news' => $success];
    }

    /**
     * @return array<string, int>
     */
    private static function memberRoute(int $success): array
    {
        return ['anon' => 401, 'outsider' => 403, 'member' => $success, 'other' => $success, 'admin' => $success, 'global' => $success, 'news' => 403];
    }

    /**
     * @return array<string, int>
     */
    private static function adminRoute(int $success): array
    {
        return ['anon' => 401, 'outsider' => 403, 'member' => 403, 'other' => 403, 'admin' => $success, 'global' => $success, 'news' => 403];
    }

    private function uploadedTextFile(): UploadedFile
    {
        $path = (string)tempnam(sys_get_temp_dir(), 'kanban-acl-');
        file_put_contents($path, 'hello upload');
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, 'upload.txt', 'text/plain', null, true);
    }

    private function uploadedImageFile(): UploadedFile
    {
        $path = (string)tempnam(sys_get_temp_dir(), 'kanban-acl-');
        file_put_contents($path, self::imagePngBytes());
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, 'avatar.png', 'image/png', null, true);
    }

    private static function imagePngBytes(): string
    {
        $image = imagecreatetruecolor(64, 64);
        self::assertNotFalse($image);
        ob_start();
        imagepng($image);

        return (string)ob_get_clean();
    }

    /**
     * Actors: member authored the seeded card, comment and news post; other is a
     * plain member; admin holds the project admin role; global is a global admin
     * with no project membership; news holds the news-maker role; outsider has no
     * access at all; candidate is unconfirmed with a pending token.
     *
     * @return array{users: array<string, User>, projectId: string, columnId: string, column2Id: string, labelId: string, cardId: string, commentId: string, newsPostId: string, fileAttachmentId: string, thumbnailAttachmentId: string, commentAttachmentId: string, confirmationToken: string}
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
        foreach (['outsider', 'member', 'other', 'admin', 'global', 'news', 'candidate'] as $name) {
            $user = User::create(Uuid::uuid4(), sprintf('%s@acl.test', $name), $passwordHash, ucfirst($name));
            if ($name === 'global') {
                $user->promoteToGlobalAdmin();
            }
            if ($name === 'news') {
                $user->promoteToNewsMaker();
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
        $card->changeRecurrence(RecurrenceRule::weekly([1, 2, 3]));
        $this->repo(CardRepository::class)->save($card);

        $comment = Comment::create(Uuid::uuid4(), $card->id(), $users['member']->id(), 'Seed comment');
        $this->repo(CommentRepository::class)->save($comment);

        $newsPost = NewsPost::create(Uuid::uuid4(), $users['member']->id(), 'Seed news');
        $this->repo(NewsPostRepository::class)->save($newsPost);

        $attachmentsRepo = $this->repo(AttachmentRepository::class);
        $fileAttachment = Attachment::create(
            Uuid::uuid4(),
            $card->id(),
            null,
            $users['member']->id(),
            'notes.txt',
            'acl/notes.txt',
            null,
            'text/plain',
            12,
        );
        $imageAttachment = Attachment::create(
            Uuid::uuid4(),
            $card->id(),
            null,
            $users['member']->id(),
            'photo.png',
            'acl/photo.png',
            'acl/photo-thumb.webp',
            'image/png',
            12,
            10,
            10,
        );
        $commentAttachment = Attachment::create(
            Uuid::uuid4(),
            $card->id(),
            $comment->id(),
            $users['member']->id(),
            'comment.txt',
            'acl/comment.txt',
            null,
            'text/plain',
            12,
        );
        $attachmentsRepo->save($fileAttachment);
        $attachmentsRepo->save($imageAttachment);
        $attachmentsRepo->save($commentAttachment);

        $storage = self::getContainer()->get(FileStorage::class);
        $storage->write('acl/notes.txt', 'hello upload');
        $storage->write('acl/photo-thumb.webp', 'thumbnail');

        $avatarKey = 'avatars/' . $users['member']->id()->toString() . '/acl.webp';
        $users['member']->changeAvatar($avatarKey);
        $usersRepo->save($users['member']);
        $storage->write($avatarKey, self::imagePngBytes());

        $membersRepo = $this->repo(ProjectMemberRepository::class);
        $membersRepo->save(ProjectMember::create(Uuid::uuid4(), $project->id(), $users['member']->id(), MemberRole::Member));
        $membersRepo->save(ProjectMember::create(Uuid::uuid4(), $project->id(), $users['other']->id(), MemberRole::Member));
        $membersRepo->save(ProjectMember::create(Uuid::uuid4(), $project->id(), $users['admin']->id(), MemberRole::Admin));

        return [
            'users' => $users,
            'projectId' => (string)$project->id(),
            'columnId' => (string)$todo->id(),
            'column2Id' => (string)$done->id(),
            'labelId' => (string)$label->id(),
            'cardId' => (string)$card->id(),
            'commentId' => (string)$comment->id(),
            'newsPostId' => (string)$newsPost->id(),
            'fileAttachmentId' => (string)$fileAttachment->id(),
            'thumbnailAttachmentId' => (string)$imageAttachment->id(),
            'commentAttachmentId' => (string)$commentAttachment->id(),
            'confirmationToken' => $confirmationToken,
        ];
    }

    /**
     * @param array{users: array<string, User>, projectId: string, columnId: string, column2Id: string, labelId: string, cardId: string, commentId: string, newsPostId: string, fileAttachmentId: string, thumbnailAttachmentId: string, commentAttachmentId: string, confirmationToken: string} $fx
     *
     * @return array<string, array{0: string, 1: string, 2: array<string, mixed>|null, 3?: array<string, UploadedFile>}>
     */
    private function routes(array $fx): array
    {
        $project = $fx['projectId'];
        $column = $fx['columnId'];
        $otherColumn = $fx['column2Id'];
        $card = $fx['cardId'];
        $comment = $fx['commentId'];
        $label = $fx['labelId'];
        $memberId = (string)$fx['users']['member']->id();
        $otherId = (string)$fx['users']['other']->id();
        $candidateId = (string)$fx['users']['candidate']->id();

        return [
            'api_projects_list' => ['GET', '/api/projects', null],
            'api_project_create' => ['POST', '/api/projects', ['name' => 'Created project']],
            'api_user_search' => ['GET', '/api/users/search?term=acl', null],
            'api_auth_me' => ['GET', '/api/auth/me', null],
            'api_mcp_info' => ['GET', '/api/mcp/info', null],
            'api_mcp_token' => ['POST', '/api/mcp/token', []],
            'api_call_token' => ['GET', '/api/call/token', null],
            'api_call_check' => ['GET', '/api/call/check', null],
            'api_call_rooms' => ['GET', '/api/call/rooms', null],
            'api_user_realtime_token' => ['GET', '/api/realtime/user-token', null],
            'api_user_events_list' => ['GET', '/api/user-events', null],
            'api_user_events_unread_count' => ['GET', '/api/user-events/unread-count', null],
            'api_user_events_seen' => ['POST', '/api/user-events/seen', null],
            'api_auth_password' => ['POST', '/api/auth/password', ['currentPassword' => 'secret123', 'newPassword' => 'newsecret123', 'newPasswordConfirmation' => 'newsecret123']],
            'api_user_avatar_update' => ['POST', '/api/users/me/avatar', null, ['file' => $this->uploadedImageFile()]],
            'api_user_avatar_delete' => ['DELETE', '/api/users/me/avatar', null],
            'api_user_avatar_get' => ['GET', '/api/users/' . $memberId . '/avatar', null],
            'api_user_color_update' => ['PUT', '/api/users/me/color', ['color' => '#112233']],
            'api_auth_register' => ['POST', '/api/auth/register', ['email' => 'newcomer@acl.test', 'password' => 'secret123', 'passwordConfirmation' => 'secret123', 'displayName' => 'Newcomer']],
            'api_auth_confirm' => ['POST', '/api/auth/confirm', ['token' => $fx['confirmationToken']]],
            'api_auth_login' => ['POST', '/api/auth/login', ['email' => 'member@acl.test', 'password' => 'secret123']],
            'api_ping' => ['GET', '/api/ping', null],

            'api_project_get' => ['GET', "/api/projects/$project", null],
            'api_board_get' => ['GET', "/api/projects/$project/board", null],
            'api_realtime_token' => ['GET', "/api/realtime/token?projectId=$project", null],
            'api_call_join' => ['POST', "/api/projects/$project/call/join", ['sessionId' => 'acl-session-1']],
            'api_call_leave' => ['POST', "/api/projects/$project/call/leave", ['sessionId' => 'acl-session-1']],
            'api_call_heartbeat' => ['POST', "/api/projects/$project/call/heartbeat", ['sessionId' => 'acl-session-1']],
            'api_call_signal' => [
                'POST',
                "/api/projects/$project/call/signal",
                ['sessionId' => 'acl-session-1', 'toUserId' => $otherId, 'toSessionId' => 'acl-session-2', 'kind' => 'offer', 'data' => ['sdp' => 'x']]
            ],
            'api_call_invite' => ['POST', "/api/projects/$project/call/invite", ['userId' => $otherId]],
            'api_call_decline' => ['POST', "/api/projects/$project/call/decline", ['fromUserId' => $memberId]],
            'api_column_create' => ['POST', "/api/projects/$project/columns", ['name' => 'New column']],
            'api_column_rename' => ['PUT', "/api/columns/$column", ['name' => 'Renamed column']],
            'api_column_reorder' => ['PUT', "/api/columns/$column/reorder", ['targetIndex' => 1]],
            'api_card_create' => ['POST', "/api/columns/$column/cards", ['title' => 'Created card']],
            'api_card_get' => ['GET', "/api/cards/$card", null],
            'api_card_seen' => ['POST', "/api/cards/$card/seen", null],
            'api_card_move' => ['PUT', "/api/cards/$card/move", ['targetColumnId' => $otherColumn, 'targetIndex' => 0]],
            'api_card_labels' => ['PUT', "/api/cards/$card/labels", ['labelIds' => [$label]]],
            'api_card_assignees' => ['PUT', "/api/cards/$card/assignees", ['userIds' => [$memberId]]],
            'api_card_executor' => ['PUT', "/api/cards/$card/executor", ['executorId' => $memberId]],
            'api_card_due_date' => ['PUT', "/api/cards/$card/due-date", ['dueDate' => '2026-12-01']],
            'api_card_linked_card' => ['PUT', "/api/cards/$card/linked-card", ['linkedCardId' => null]],
            'api_card_recurrence' => ['PUT', "/api/cards/$card/recurrence", ['recurrence' => ['kind' => 'weekly', 'weekdays' => [1, 2, 3]]]],
            'api_card_complete' => ['POST', "/api/cards/$card/complete", null],
            'api_card_uncomplete' => ['POST', "/api/cards/$card/uncomplete", null],
            'api_card_comment_create' => ['POST', "/api/cards/$card/comments", ['body' => 'Created comment']],
            'api_card_attachment_create' => ['POST', "/api/cards/$card/attachments", null, ['file' => $this->uploadedTextFile()]],
            'api_card_attachment_delete' => ['DELETE', "/api/cards/$card/attachments/{$fx['fileAttachmentId']}", null],
            'api_comment_attachment_delete' => ['DELETE', "/api/comments/$comment/attachments/{$fx['commentAttachmentId']}", null],
            'api_attachment_download' => ['GET', "/api/attachments/{$fx['fileAttachmentId']}", null],
            'api_attachment_thumbnail' => ['GET', "/api/attachments/{$fx['thumbnailAttachmentId']}/thumbnail", null],
            'api_label_create' => ['POST', "/api/projects/$project/labels", ['name' => 'New label', 'color' => '#00ff00']],
            'api_label_update' => ['PUT', "/api/labels/$label", ['name' => 'Renamed label', 'color' => '#0000ff']],

            'api_project_rename' => ['PUT', "/api/projects/$project", ['name' => 'Renamed project']],
            'api_project_archive' => ['POST', "/api/projects/$project/archive", null],
            'api_project_restore' => ['POST', "/api/projects/$project/restore", null],
            'api_project_set_favourite' => ['PUT', "/api/projects/$project/favourite", ['isFavourite' => true]],
            'api_project_presence_heartbeat' => ['POST', "/api/projects/$project/presence/heartbeat", null],
            'api_project_member_add' => ['POST', "/api/projects/$project/members", ['userId' => $candidateId, 'role' => 'member']],
            'api_project_member_change_role' => ['PUT', "/api/projects/$project/members/$otherId", ['role' => 'admin']],
            'api_project_member_remove' => ['DELETE', "/api/projects/$project/members/$otherId", null],
            'api_column_delete' => ['DELETE', "/api/columns/$otherColumn", null],
            'api_label_delete' => ['DELETE', "/api/labels/$label", null],

            'api_card_update' => ['PUT', "/api/cards/$card", ['title' => 'Edited card', 'description' => '']],
            'api_card_archive' => ['DELETE', "/api/cards/$card", null],
            'api_card_restore' => ['POST', "/api/cards/$card/restore", null],

            'api_card_comment_update' => ['PUT', "/api/comments/$comment", ['body' => 'Edited comment']],
            'api_card_comment_delete' => ['DELETE', "/api/comments/$comment", null],

            'api_news_list' => ['GET', '/api/news', null],
            'api_news_create' => ['POST', '/api/news', ['body' => 'Created news']],
            'api_news_update' => ['PUT', "/api/news/{$fx['newsPostId']}", ['body' => 'Edited news']],
            'api_news_delete' => ['DELETE', "/api/news/{$fx['newsPostId']}", null],

            'api_project_delete' => ['DELETE', "/api/projects/$project", null],

            'api_users_list' => ['GET', '/api/users', null],
            'api_user_confirm' => ['POST', "/api/users/$candidateId/confirm", null],
            'api_user_promote_news_maker' => ['POST', "/api/users/$candidateId/news-maker", null],
            'api_user_demote_news_maker' => ['DELETE', "/api/users/$candidateId/news-maker", null],
        ];
    }
}

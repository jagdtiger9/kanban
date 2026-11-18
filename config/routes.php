<?php

namespace Symfony\Component\Routing\Loader\Configurator;

use App\Board\Infrastructure\Controller\AttachmentController;
use App\Board\Infrastructure\Controller\BoardController;
use App\Board\Infrastructure\Controller\RealtimeController;
use App\Common\Infrastructure\Controller\PingController;
use App\Mcp\Infrastructure\Controller\McpTokenController;
use App\News\Infrastructure\Controller\NewsController;
use App\Project\Infrastructure\Controller\ProjectController;
use App\User\Infrastructure\Controller\AuthController;
use App\User\Infrastructure\Controller\UserAvatarController;
use App\User\Infrastructure\Controller\UserController;

// Explicit API routes live here (routing stays in config, not in controllers).
// routing.controllers additionally imports any controller action carrying #[Route].
$config = Routes::config([
    'controllers' => [
        'resource' => 'routing.controllers',
    ],
    'api_ping' => [
        'path' => '/api/ping',
        'controller' => [PingController::class, 'ping'],
        'methods' => ['GET'],
    ],
    'login_check' => [
        'path' => '/login_check',
        'methods' => ['GET'],
    ],
    'api_auth_login' => [
        'path' => '/api/auth/login',
        'controller' => [AuthController::class, 'login'],
        'methods' => ['POST'],
    ],
    'api_auth_register' => [
        'path' => '/api/auth/register',
        'controller' => [AuthController::class, 'register'],
        'methods' => ['POST'],
    ],
    'api_auth_confirm' => [
        'path' => '/api/auth/confirm',
        'controller' => [AuthController::class, 'confirm'],
        'methods' => ['POST'],
    ],
    'api_auth_password' => [
        'path' => '/api/auth/password',
        'controller' => [AuthController::class, 'changePassword'],
        'methods' => ['POST'],
    ],
    'api_auth_me' => [
        'path' => '/api/auth/me',
        'controller' => [AuthController::class, 'me'],
        'methods' => ['GET'],
    ],
    'api_mcp_token' => [
        'path' => '/api/mcp/token',
        'controller' => [McpTokenController::class, 'issue'],
        'methods' => ['POST'],
    ],
    'api_projects_list' => [
        'path' => '/api/projects',
        'controller' => [ProjectController::class, 'listProjects'],
        'methods' => ['GET'],
    ],
    'api_project_create' => [
        'path' => '/api/projects',
        'controller' => [ProjectController::class, 'create'],
        'methods' => ['POST'],
    ],
    'api_project_get' => [
        'path' => '/api/projects/{projectId}',
        'controller' => [ProjectController::class, 'get'],
        'methods' => ['GET'],
    ],
    'api_project_rename' => [
        'path' => '/api/projects/{projectId}',
        'controller' => [ProjectController::class, 'rename'],
        'methods' => ['PUT'],
    ],
    'api_project_delete' => [
        'path' => '/api/projects/{projectId}',
        'controller' => [ProjectController::class, 'delete'],
        'methods' => ['DELETE'],
    ],
    'api_project_archive' => [
        'path' => '/api/projects/{projectId}/archive',
        'controller' => [ProjectController::class, 'archive'],
        'methods' => ['POST'],
    ],
    'api_project_restore' => [
        'path' => '/api/projects/{projectId}/restore',
        'controller' => [ProjectController::class, 'restore'],
        'methods' => ['POST'],
    ],
    'api_project_member_add' => [
        'path' => '/api/projects/{projectId}/members',
        'controller' => [ProjectController::class, 'addMember'],
        'methods' => ['POST'],
    ],
    'api_project_member_change_role' => [
        'path' => '/api/projects/{projectId}/members/{userId}',
        'controller' => [ProjectController::class, 'changeMemberRole'],
        'methods' => ['PUT'],
    ],
    'api_project_member_remove' => [
        'path' => '/api/projects/{projectId}/members/{userId}',
        'controller' => [ProjectController::class, 'removeMember'],
        'methods' => ['DELETE'],
    ],
    'api_user_search' => [
        'path' => '/api/users/search',
        'controller' => [UserController::class, 'search'],
        'methods' => ['GET'],
    ],
    'api_users_list' => [
        'path' => '/api/users',
        'controller' => [UserController::class, 'list'],
        'methods' => ['GET'],
    ],
    'api_user_confirm' => [
        'path' => '/api/users/{userId}/confirm',
        'controller' => [UserController::class, 'confirmUser'],
        'methods' => ['POST'],
    ],
    'api_user_promote_news_maker' => [
        'path' => '/api/users/{userId}/news-maker',
        'controller' => [UserController::class, 'promoteNewsMaker'],
        'methods' => ['POST'],
    ],
    'api_user_demote_news_maker' => [
        'path' => '/api/users/{userId}/news-maker',
        'controller' => [UserController::class, 'demoteNewsMaker'],
        'methods' => ['DELETE'],
    ],
    'api_news_list' => [
        'path' => '/api/news',
        'controller' => [NewsController::class, 'list'],
        'methods' => ['GET'],
    ],
    'api_news_create' => [
        'path' => '/api/news',
        'controller' => [NewsController::class, 'create'],
        'methods' => ['POST'],
    ],
    'api_news_update' => [
        'path' => '/api/news/{newsPostId}',
        'controller' => [NewsController::class, 'update'],
        'methods' => ['PUT'],
    ],
    'api_news_delete' => [
        'path' => '/api/news/{newsPostId}',
        'controller' => [NewsController::class, 'delete'],
        'methods' => ['DELETE'],
    ],
    'api_user_avatar_update' => [
        'path' => '/api/users/me/avatar',
        'controller' => [UserAvatarController::class, 'update'],
        'methods' => ['POST'],
    ],
    'api_user_avatar_delete' => [
        'path' => '/api/users/me/avatar',
        'controller' => [UserAvatarController::class, 'delete'],
        'methods' => ['DELETE'],
    ],
    'api_user_avatar_get' => [
        'path' => '/api/users/{userId}/avatar',
        'controller' => [UserAvatarController::class, 'get'],
        'methods' => ['GET'],
    ],
    'api_board_get' => [
        'path' => '/api/projects/{projectId}/board',
        'controller' => [BoardController::class, 'get'],
        'methods' => ['GET'],
    ],
    'api_realtime_token' => [
        'path' => '/api/realtime/token',
        'controller' => [RealtimeController::class, 'token'],
        'methods' => ['GET'],
    ],
    'api_column_create' => [
        'path' => '/api/projects/{projectId}/columns',
        'controller' => [BoardController::class, 'createColumn'],
        'methods' => ['POST'],
    ],
    'api_column_rename' => [
        'path' => '/api/columns/{columnId}',
        'controller' => [BoardController::class, 'renameColumn'],
        'methods' => ['PUT'],
    ],
    'api_column_delete' => [
        'path' => '/api/columns/{columnId}',
        'controller' => [BoardController::class, 'deleteColumn'],
        'methods' => ['DELETE'],
    ],
    'api_column_reorder' => [
        'path' => '/api/columns/{columnId}/reorder',
        'controller' => [BoardController::class, 'reorderColumn'],
        'methods' => ['PUT'],
    ],
    'api_card_create' => [
        'path' => '/api/columns/{columnId}/cards',
        'controller' => [BoardController::class, 'createCard'],
        'methods' => ['POST'],
    ],
    'api_card_update' => [
        'path' => '/api/cards/{cardId}',
        'controller' => [BoardController::class, 'updateCard'],
        'methods' => ['PUT'],
    ],
    'api_card_archive' => [
        'path' => '/api/cards/{cardId}',
        'controller' => [BoardController::class, 'archiveCard'],
        'methods' => ['DELETE'],
    ],
    'api_card_restore' => [
        'path' => '/api/cards/{cardId}/restore',
        'controller' => [BoardController::class, 'restoreCard'],
        'methods' => ['POST'],
    ],
    'api_card_move' => [
        'path' => '/api/cards/{cardId}/move',
        'controller' => [BoardController::class, 'moveCard'],
        'methods' => ['PUT'],
    ],
    'api_card_labels' => [
        'path' => '/api/cards/{cardId}/labels',
        'controller' => [BoardController::class, 'setCardLabels'],
        'methods' => ['PUT'],
    ],
    'api_card_assignees' => [
        'path' => '/api/cards/{cardId}/assignees',
        'controller' => [BoardController::class, 'setCardAssignees'],
        'methods' => ['PUT'],
    ],
    'api_card_due_date' => [
        'path' => '/api/cards/{cardId}/due-date',
        'controller' => [BoardController::class, 'setCardDueDate'],
        'methods' => ['PUT'],
    ],
    'api_card_get' => [
        'path' => '/api/cards/{cardId}',
        'controller' => [BoardController::class, 'getCard'],
        'methods' => ['GET'],
    ],
    'api_card_comment_create' => [
        'path' => '/api/cards/{cardId}/comments',
        'controller' => [BoardController::class, 'createComment'],
        'methods' => ['POST'],
    ],
    'api_card_comment_update' => [
        'path' => '/api/comments/{commentId}',
        'controller' => [BoardController::class, 'updateComment'],
        'methods' => ['PUT'],
    ],
    'api_card_comment_delete' => [
        'path' => '/api/comments/{commentId}',
        'controller' => [BoardController::class, 'deleteComment'],
        'methods' => ['DELETE'],
    ],
    'api_card_attachment_create' => [
        'path' => '/api/cards/{cardId}/attachments',
        'controller' => [AttachmentController::class, 'createCardAttachment'],
        'methods' => ['POST'],
    ],
    'api_card_attachment_delete' => [
        'path' => '/api/cards/{cardId}/attachments/{attachmentId}',
        'controller' => [AttachmentController::class, 'deleteCardAttachment'],
        'methods' => ['DELETE'],
    ],
    'api_comment_attachment_delete' => [
        'path' => '/api/comments/{commentId}/attachments/{attachmentId}',
        'controller' => [AttachmentController::class, 'deleteCommentAttachment'],
        'methods' => ['DELETE'],
    ],
    'api_attachment_download' => [
        'path' => '/api/attachments/{attachmentId}',
        'controller' => [AttachmentController::class, 'download'],
        'methods' => ['GET'],
    ],
    'api_attachment_thumbnail' => [
        'path' => '/api/attachments/{attachmentId}/thumbnail',
        'controller' => [AttachmentController::class, 'thumbnail'],
        'methods' => ['GET'],
    ],
    'api_label_create' => [
        'path' => '/api/projects/{projectId}/labels',
        'controller' => [BoardController::class, 'createLabel'],
        'methods' => ['POST'],
    ],
    'api_label_update' => [
        'path' => '/api/labels/{labelId}',
        'controller' => [BoardController::class, 'updateLabel'],
        'methods' => ['PUT'],
    ],
    'api_label_delete' => [
        'path' => '/api/labels/{labelId}',
        'controller' => [BoardController::class, 'deleteLabel'],
        'methods' => ['DELETE'],
    ],
]);

return $config;

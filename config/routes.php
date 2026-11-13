<?php

# Pre-migration config/routes.yaml, kept for reference:
#
# yaml-language-server: $schema=../vendor/symfony/routing/Loader/schema/routing.schema.json
#
# Explicit API routes live here (routing stays in config, not in controllers).
# routing.controllers additionally imports any controller action carrying #[Route].
#
# controllers:
#     resource: routing.controllers
#
# api_ping:
#     path: /api/ping
#     controller: App\Common\Infrastructure\Controller\PingController::ping
#     methods: GET
# login_check:
#     path: /login_check
#     methods: [GET]
#
# api_auth_login:
#     path: /api/auth/login
#     controller: App\User\Infrastructure\Controller\AuthController::login
#     methods: POST
#
# api_auth_register:
#     path: /api/auth/register
#     controller: App\User\Infrastructure\Controller\AuthController::register
#     methods: POST
#
# api_auth_me:
#     path: /api/auth/me
#     controller: App\User\Infrastructure\Controller\AuthController::me
#     methods: GET
#
# api_projects_list:
#     path: /api/projects
#     controller: App\Project\Infrastructure\Controller\ProjectController::listProjects
#     methods: GET
#
# api_project_create:
#     path: /api/projects
#     controller: App\Project\Infrastructure\Controller\ProjectController::create
#     methods: POST
#
# api_project_get:
#     path: /api/projects/{projectId}
#     controller: App\Project\Infrastructure\Controller\ProjectController::get
#     methods: GET
#
# api_project_rename:
#     path: /api/projects/{projectId}
#     controller: App\Project\Infrastructure\Controller\ProjectController::rename
#     methods: PUT
#
# api_project_delete:
#     path: /api/projects/{projectId}
#     controller: App\Project\Infrastructure\Controller\ProjectController::delete
#     methods: DELETE
#
# api_project_archive:
#     path: /api/projects/{projectId}/archive
#     controller: App\Project\Infrastructure\Controller\ProjectController::archive
#     methods: POST
#
# api_project_restore:
#     path: /api/projects/{projectId}/restore
#     controller: App\Project\Infrastructure\Controller\ProjectController::restore
#     methods: POST
#
# api_project_member_add:
#     path: /api/projects/{projectId}/members
#     controller: App\Project\Infrastructure\Controller\ProjectController::addMember
#     methods: POST
#
# api_project_member_change_role:
#     path: /api/projects/{projectId}/members/{userId}
#     controller: App\Project\Infrastructure\Controller\ProjectController::changeMemberRole
#     methods: PUT
#
# api_project_member_remove:
#     path: /api/projects/{projectId}/members/{userId}
#     controller: App\Project\Infrastructure\Controller\ProjectController::removeMember
#     methods: DELETE
#
# api_user_search:
#     path: /api/users/search
#     controller: App\User\Infrastructure\Controller\UserController::search
#     methods: GET
#
# api_board_get:
#     path: /api/projects/{projectId}/board
#     controller: App\Board\Infrastructure\Controller\BoardController::get
#     methods: GET
#
# api_column_create:
#     path: /api/projects/{projectId}/columns
#     controller: App\Board\Infrastructure\Controller\BoardController::createColumn
#     methods: POST
#
# api_column_rename:
#     path: /api/columns/{columnId}
#     controller: App\Board\Infrastructure\Controller\BoardController::renameColumn
#     methods: PUT
#
# api_column_delete:
#     path: /api/columns/{columnId}
#     controller: App\Board\Infrastructure\Controller\BoardController::deleteColumn
#     methods: DELETE
#
# api_column_reorder:
#     path: /api/columns/{columnId}/reorder
#     controller: App\Board\Infrastructure\Controller\BoardController::reorderColumn
#     methods: PUT
#
# api_card_create:
#     path: /api/columns/{columnId}/cards
#     controller: App\Board\Infrastructure\Controller\BoardController::createCard
#     methods: POST
#
# api_card_update:
#     path: /api/cards/{cardId}
#     controller: App\Board\Infrastructure\Controller\BoardController::updateCard
#     methods: PUT
#
# api_card_archive:
#     path: /api/cards/{cardId}
#     controller: App\Board\Infrastructure\Controller\BoardController::archiveCard
#     methods: DELETE
#
# api_card_restore:
#     path: /api/cards/{cardId}/restore
#     controller: App\Board\Infrastructure\Controller\BoardController::restoreCard
#     methods: POST
#
# api_card_move:
#     path: /api/cards/{cardId}/move
#     controller: App\Board\Infrastructure\Controller\BoardController::moveCard
#     methods: PUT
#
# api_card_labels:
#     path: /api/cards/{cardId}/labels
#     controller: App\Board\Infrastructure\Controller\BoardController::setCardLabels
#     methods: PUT
#
# api_card_assignees:
#     path: /api/cards/{cardId}/assignees
#     controller: App\Board\Infrastructure\Controller\BoardController::setCardAssignees
#     methods: PUT
#
# api_card_due_date:
#     path: /api/cards/{cardId}/due-date
#     controller: App\Board\Infrastructure\Controller\BoardController::setCardDueDate
#     methods: PUT
#
# api_card_get:
#     path: /api/cards/{cardId}
#     controller: App\Board\Infrastructure\Controller\BoardController::getCard
#     methods: GET
#
# api_card_comment_create:
#     path: /api/cards/{cardId}/comments
#     controller: App\Board\Infrastructure\Controller\BoardController::createComment
#     methods: POST
#
# api_card_comment_update:
#     path: /api/comments/{commentId}
#     controller: App\Board\Infrastructure\Controller\BoardController::updateComment
#     methods: PUT
#
# api_card_comment_delete:
#     path: /api/comments/{commentId}
#     controller: App\Board\Infrastructure\Controller\BoardController::deleteComment
#     methods: DELETE
#
# api_label_create:
#     path: /api/projects/{projectId}/labels
#     controller: App\Board\Infrastructure\Controller\BoardController::createLabel
#     methods: POST
#
# api_label_update:
#     path: /api/labels/{labelId}
#     controller: App\Board\Infrastructure\Controller\BoardController::updateLabel
#     methods: PUT
#
# api_label_delete:
#     path: /api/labels/{labelId}
#     controller: App\Board\Infrastructure\Controller\BoardController::deleteLabel
#     methods: DELETE

namespace Symfony\Component\Routing\Loader\Configurator;

use App\Board\Infrastructure\Controller\BoardController;
use App\Common\Infrastructure\Controller\PingController;
use App\Project\Infrastructure\Controller\ProjectController;
use App\User\Infrastructure\Controller\AuthController;
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
    'api_auth_me' => [
        'path' => '/api/auth/me',
        'controller' => [AuthController::class, 'me'],
        'methods' => ['GET'],
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
    'api_board_get' => [
        'path' => '/api/projects/{projectId}/board',
        'controller' => [BoardController::class, 'get'],
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

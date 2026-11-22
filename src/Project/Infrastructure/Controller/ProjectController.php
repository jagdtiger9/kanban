<?php

declare(strict_types=1);

namespace App\Project\Infrastructure\Controller;

use Aljerom\SymfonyBoilerplate\CQRS\CommandBus;
use Aljerom\SymfonyBoilerplate\CQRS\QueryBus;
use App\Project\Application\Message\Command\AddProjectMemberCommand;
use App\Project\Application\Message\Command\ArchiveProjectCommand;
use App\Project\Application\Message\Command\ChangeProjectMemberRoleCommand;
use App\Project\Application\Message\Command\CreateProjectCommand;
use App\Project\Application\Message\Command\HardDeleteProjectCommand;
use App\Project\Application\Message\Command\RemoveProjectMemberCommand;
use App\Project\Application\Message\Command\RenameProjectCommand;
use App\Project\Application\Message\Command\RestoreProjectCommand;
use App\Project\Application\Message\Command\SetProjectFavouriteCommand;
use App\Project\Application\Message\Query\GetProjectQuery;
use App\Project\Application\Message\Query\ListProjectsQuery;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Enum\MemberRole;
use App\Shared\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class ProjectController extends AbstractController
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
    ) {
    }

    public function listProjects(ListProjectsQuery $query, Security $security): JsonResponse
    {
        $query->actorId = $this->actorId($security);

        return $this->json(
            array_map(
                fn(array $item): array => $this->projectPayload(
                    $item['project'],
                    $item['role'],
                    $item['memberCount'],
                    $item['isFavourite'],
                ),
                $this->queryBus->query($query),
            )
        );
    }

    public function create(CreateProjectCommand $command, Security $security): JsonResponse
    {
        $command->actorId = $this->actorId($security);

        return $this->json(
            $this->projectPayload($this->commandBus->dispatch($command), MemberRole::Admin, 1, true),
            Response::HTTP_CREATED,
        );
    }

    public function get(GetProjectQuery $query, Security $security): JsonResponse
    {
        $query->actorId = $this->actorId($security);

        $result = $this->queryBus->query($query);

        return $this->json([
            'project' => $this->projectPayload($result['project'], $result['role']),
            'boardId' => (string)$result['boardId'],
            'members' => array_map(
                fn(array $member): array => [
                    'userId' => (string)$member['userId'],
                    'role' => $member['role']->value,
                    'email' => $member['email'],
                    'displayName' => $member['displayName'],
                    'avatarVersion' => $member['avatarVersion'],
                    'avatarColor' => $member['avatarColor'],
                    'joinedAt' => $member['joinedAt']->format(\DateTimeInterface::ATOM),
                ],
                $result['members'],
            ),
        ]);
    }

    public function rename(RenameProjectCommand $command, Security $security): JsonResponse
    {
        $command->actorId = $this->actorId($security);

        return $this->json($this->projectPayload($this->commandBus->dispatch($command)));
    }

    public function archive(ArchiveProjectCommand $command, Security $security): JsonResponse
    {
        $command->actorId = $this->actorId($security);

        return $this->json($this->projectPayload($this->commandBus->dispatch($command)));
    }

    public function restore(RestoreProjectCommand $command, Security $security): JsonResponse
    {
        $command->actorId = $this->actorId($security);

        return $this->json($this->projectPayload($this->commandBus->dispatch($command)));
    }

    public function delete(HardDeleteProjectCommand $command, Security $security): Response
    {
        $command->actorId = $this->actorId($security);
        $this->commandBus->dispatch($command);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function setFavourite(SetProjectFavouriteCommand $command, Security $security): Response
    {
        $command->actorId = $this->actorId($security);
        $this->commandBus->dispatch($command);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function addMember(AddProjectMemberCommand $command, Security $security): JsonResponse
    {
        $command->actorId = $this->actorId($security);

        return $this->json(
            $this->memberPayload($this->commandBus->dispatch($command)),
            Response::HTTP_CREATED,
        );
    }

    public function changeMemberRole(ChangeProjectMemberRoleCommand $command, Security $security): JsonResponse
    {
        $command->actorId = $this->actorId($security);

        return $this->json($this->memberPayload($this->commandBus->dispatch($command)));
    }

    public function removeMember(RemoveProjectMemberCommand $command, Security $security): Response
    {
        $command->actorId = $this->actorId($security);
        $this->commandBus->dispatch($command);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function actorId(Security $security): string
    {
        $user = $security->getUser();
        if (!$user instanceof SecurityUser) {
            throw new \LogicException('Unexpected user type');
        }

        return (string)$user->getId();
    }

    /**
     * @return array{id: string, name: string, archived: bool, createdAt: string, role?: string, memberCount?: int, isFavourite?: bool}
     */
    private function projectPayload(
        Project $project,
        ?MemberRole $role = null,
        ?int $memberCount = null,
        ?bool $isFavourite = null,
    ): array {
        $payload = [
            'id' => (string)$project->id(),
            'name' => $project->name(),
            'archived' => $project->isArchived(),
            'createdAt' => $project->createdAt()->format(\DateTimeInterface::ATOM),
        ];
        if ($role !== null) {
            $payload['role'] = $role->value;
        }
        if ($memberCount !== null) {
            $payload['memberCount'] = $memberCount;
        }
        if ($isFavourite !== null) {
            $payload['isFavourite'] = $isFavourite;
        }

        return $payload;
    }

    /**
     * @return array{userId: string, role: string, joinedAt: string}
     */
    private function memberPayload(ProjectMember $member): array
    {
        return [
            'userId' => (string)$member->userId(),
            'role' => $member->role()->value,
            'joinedAt' => $member->joinedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}

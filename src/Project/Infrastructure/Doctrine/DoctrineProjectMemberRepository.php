<?php

declare(strict_types=1);

namespace App\Project\Infrastructure\Doctrine;

use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Repository\ProjectMemberRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ProjectMemberRepository::class)]
final class DoctrineProjectMemberRepository extends ServiceEntityRepository implements ProjectMemberRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectMember::class);
    }

    public function save(ProjectMember $member): void
    {
        $em = $this->getEntityManager();
        $em->persist($member);
        $em->flush();
    }

    public function findByProjectAndUser(UuidInterface $projectId, UuidInterface $userId): ?ProjectMember
    {
        return $this->findOneBy(['projectId' => $projectId, 'userId' => $userId]);
    }

    public function findByProject(UuidInterface $projectId): array
    {
        return $this->findBy(['projectId' => $projectId], ['joinedAt' => 'ASC']);
    }

    public function findByUser(UuidInterface $userId): array
    {
        return $this->findBy(['userId' => $userId]);
    }

    public function countByProjects(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('m')
            ->select('m.projectId AS projectId, COUNT(m.id) AS memberCount')
            ->where('m.projectId IN (:projectIds)')
            ->setParameter('projectIds', $projectIds)
            ->groupBy('m.projectId')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string)$row['projectId']] = (int)$row['memberCount'];
        }

        return $counts;
    }

    public function remove(ProjectMember $member): void
    {
        $em = $this->getEntityManager();
        $em->remove($member);
        $em->flush();
    }
}

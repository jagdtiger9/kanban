<?php

declare(strict_types=1);

namespace App\Project\Infrastructure\Doctrine;

use App\Project\Domain\Entity\Project;
use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Repository\ProjectRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ProjectRepository::class)]
final class DoctrineProjectRepository extends ServiceEntityRepository implements ProjectRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    public function save(Project $project): void
    {
        $em = $this->getEntityManager();
        $em->persist($project);
        $em->flush();
    }

    public function findById(UuidInterface $id): ?Project
    {
        return $this->findOneBy(['id' => $id]);
    }

    public function findByMember(UuidInterface $userId): array
    {
        return $this->createQueryBuilder('p')
            ->join(ProjectMember::class, 'm', 'WITH', 'm.projectId = p.id')
            ->where('m.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findAll(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function remove(Project $project): void
    {
        $em = $this->getEntityManager();
        $em->remove($project);
        $em->flush();
    }
}

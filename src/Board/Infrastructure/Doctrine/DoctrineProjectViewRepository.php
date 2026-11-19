<?php

declare(strict_types=1);

namespace App\Board\Infrastructure\Doctrine;

use App\Board\Domain\Entity\ProjectView;
use App\Board\Domain\Repository\ProjectViewRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ProjectViewRepository::class)]
final class DoctrineProjectViewRepository extends ServiceEntityRepository implements ProjectViewRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectView::class);
    }

    public function save(ProjectView $view): void
    {
        $em = $this->getEntityManager();
        $em->persist($view);
        $em->flush();
    }

    public function findByUserAndProject(UuidInterface $userId, UuidInterface $projectId): ?ProjectView
    {
        return $this->findOneBy(['userId' => $userId, 'projectId' => $projectId]);
    }
}

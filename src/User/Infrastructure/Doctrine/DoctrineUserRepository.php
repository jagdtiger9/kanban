<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Doctrine;

use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(UserRepository::class)]
final class DoctrineUserRepository extends ServiceEntityRepository implements UserRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function save(User $user): void
    {
        $em = $this->getEntityManager();
        $em->persist($user);
        $em->flush();
    }

    public function findById(UuidInterface $id): ?User
    {
        return $this->findOneBy(['id' => $id]);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function findByConfirmationTokenHash(string $tokenHash): ?User
    {
        return $this->findOneBy(['confirmationTokenHash' => $tokenHash]);
    }

    public function findAllOrderedByDisplayName(): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.displayName', 'ASC')
            ->addOrderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findBySearchTerm(string $term): array
    {
        $term = strtolower(trim($term));
        if ($term === '') {
            return [];
        }

        $pattern = '%' . addcslashes($term, '%_') . '%';

        return $this->createQueryBuilder('u')
            ->where('LOWER(u.email) LIKE :term OR LOWER(u.displayName) LIKE :term')
            ->setParameter('term', $pattern)
            ->orderBy('u.displayName', 'ASC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();
    }

    public function allAvatarStorageKeys(): array
    {
        $rows = $this->createQueryBuilder('u')
            ->select('u.avatarStorageKey')
            ->where('u.avatarStorageKey IS NOT NULL')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn(array $row): string => $row['avatarStorageKey'], $rows);
    }
}

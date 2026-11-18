<?php

declare(strict_types=1);

namespace App\Board\Infrastructure\Doctrine;

use App\Board\Domain\Entity\Attachment;
use App\Board\Domain\Repository\AttachmentRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(AttachmentRepository::class)]
final class DoctrineAttachmentRepository extends ServiceEntityRepository implements AttachmentRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Attachment::class);
    }

    public function save(Attachment $attachment): void
    {
        $em = $this->getEntityManager();
        $em->persist($attachment);
        $em->flush();
    }

    public function findById(UuidInterface $id): ?Attachment
    {
        return $this->findOneBy(['id' => $id]);
    }

    public function findCardAttachments(UuidInterface $cardId): array
    {
        return $this->findBy(
            ['cardId' => $cardId, 'commentId' => null],
            ['createdAt' => 'ASC', 'id' => 'ASC'],
        );
    }

    public function findByCard(UuidInterface $cardId): array
    {
        return $this->findBy(['cardId' => $cardId], ['createdAt' => 'ASC', 'id' => 'ASC']);
    }

    public function findByComment(UuidInterface $commentId): array
    {
        return $this->findBy(['commentId' => $commentId], ['createdAt' => 'ASC', 'id' => 'ASC']);
    }

    public function usageByCard(UuidInterface $cardId): array
    {
        $row = $this->createQueryBuilder('attachment')
            ->select('COUNT(attachment.id) AS files', 'COALESCE(SUM(attachment.sizeBytes), 0) AS bytes')
            ->where('attachment.cardId = :cardId')
            ->setParameter('cardId', $cardId)
            ->getQuery()
            ->getSingleResult();

        return ['files' => (int)$row['files'], 'bytes' => (int)$row['bytes']];
    }

    public function allStorageKeys(): array
    {
        $rows = $this->createQueryBuilder('attachment')
            ->select('attachment.storageKey AS storageKey', 'attachment.thumbnailStorageKey AS thumbnailStorageKey')
            ->getQuery()
            ->getArrayResult();

        $keys = [];
        foreach ($rows as $row) {
            $keys[] = $row['storageKey'];

            if ($row['thumbnailStorageKey'] !== null) {
                $keys[] = $row['thumbnailStorageKey'];
            }
        }

        return array_values(array_unique($keys));
    }

    public function remove(Attachment $attachment): void
    {
        $em = $this->getEntityManager();
        $em->remove($attachment);
        $em->flush();
    }
}

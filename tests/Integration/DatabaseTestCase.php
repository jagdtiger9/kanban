<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class DatabaseTestCase extends WebTestCase
{
    private static bool $schemaCreated = false;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->prepareDatabase();
    }

    /**
     * Schema bootstrap and truncation, for subclasses that must boot the kernel
     * through createClient() instead of bootKernel().
     */
    protected function prepareDatabase(): void
    {
        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        if (!self::$schemaCreated) {
            self::$schemaCreated = true;
            $metadata = $this->em->getMetadataFactory()->getAllMetadata();
            $connection = $this->em->getConnection();
            if (count($connection->createSchemaManager()->listTableNames()) > 0) {
                (new SchemaTool($this->em))->dropSchema($metadata);
            }
            (new SchemaTool($this->em))->createSchema($metadata);
        }

        $this->truncateAll();
    }

    protected function em(): EntityManagerInterface
    {
        return $this->em;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $service
     *
     * @return T
     */
    protected function repo(string $service): object
    {
        return self::getContainer()->get($service);
    }

    private function truncateAll(): void
    {
        $connection = $this->em->getConnection();
        foreach ($connection->createSchemaManager()->listTableNames() as $table) {
            $connection->executeStatement(sprintf('TRUNCATE TABLE %s RESTART IDENTITY CASCADE', $table));
        }
    }
}

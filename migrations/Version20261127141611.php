<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127141611 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cards.checklist';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cards ADD checklist JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cards DROP checklist');
    }
}

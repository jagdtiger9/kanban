<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261118215554 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add project_members.is_favourite';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project_members ADD is_favourite BOOLEAN DEFAULT false NOT NULL');
        // The entity maps no default; the temporary default only backfills existing rows.
        $this->addSql('ALTER TABLE project_members ALTER is_favourite DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project_members DROP is_favourite');
    }
}

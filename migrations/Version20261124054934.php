<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261124054934 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add project_members.last_seen_at for project presence';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project_members ADD last_seen_at TIMESTAMP(6) WITH TIME ZONE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project_members DROP last_seen_at');
    }
}

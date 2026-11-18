<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261117185412 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add avatar storage columns to users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD avatar_storage_key VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD avatar_updated_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9480E2DFA ON users (avatar_storage_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_1483A5E9480E2DFA');
        $this->addSql('ALTER TABLE users DROP avatar_storage_key');
        $this->addSql('ALTER TABLE users DROP avatar_updated_at');
    }
}

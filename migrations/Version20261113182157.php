<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113182157 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email confirmation columns to users and backfill existing users as confirmed';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD confirmed_at TIMESTAMP(6) WITH TIME ZONE');
        $this->addSql('ALTER TABLE users ADD confirmation_token_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD confirmation_token_expires_at TIMESTAMP(6) WITH TIME ZONE');
        $this->addSql('UPDATE users SET confirmed_at = created_at WHERE confirmed_at IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP confirmed_at');
        $this->addSql('ALTER TABLE users DROP confirmation_token_hash');
        $this->addSql('ALTER TABLE users DROP confirmation_token_expires_at');
    }
}

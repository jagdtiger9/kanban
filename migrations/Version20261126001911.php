<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261126001911 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add columns.kind and cards.completed_at/completed_by for sticker cards';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE columns ADD kind VARCHAR(20) DEFAULT \'task\' NOT NULL');
        $this->addSql('ALTER TABLE columns ALTER COLUMN kind DROP DEFAULT');
        $this->addSql('ALTER TABLE cards ADD completed_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE cards ADD completed_by UUID DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_cards_completed_by ON cards (completed_by)');
        // Manual FK — Doctrine cannot infer it from a plain uuid column.
        $this->addSql('ALTER TABLE cards ADD CONSTRAINT FK_CARDS_COMPLETED_BY FOREIGN KEY (completed_by) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cards DROP CONSTRAINT FK_CARDS_COMPLETED_BY');
        $this->addSql('DROP INDEX idx_cards_completed_by');
        $this->addSql('ALTER TABLE cards DROP completed_by');
        $this->addSql('ALTER TABLE cards DROP completed_at');
        $this->addSql('ALTER TABLE columns DROP kind');
    }
}

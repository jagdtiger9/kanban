<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119214515 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add project_views and card_views tables for seen tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE card_views (id UUID NOT NULL, user_id UUID NOT NULL, card_id UUID NOT NULL, last_seen_at TIMESTAMP(6) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_card_views_user_card ON card_views (user_id, card_id)');
        $this->addSql('CREATE INDEX idx_card_views_card_id ON card_views (card_id)');
        $this->addSql('CREATE TABLE project_views (id UUID NOT NULL, user_id UUID NOT NULL, project_id UUID NOT NULL, first_seen_at TIMESTAMP(6) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_project_views_user_project ON project_views (user_id, project_id)');
        $this->addSql('CREATE INDEX idx_project_views_project_id ON project_views (project_id)');
        // Manual FKs — Doctrine cannot infer them from plain uuid columns.
        $this->addSql('ALTER TABLE card_views ADD CONSTRAINT FK_CARD_VIEWS_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE card_views ADD CONSTRAINT FK_CARD_VIEWS_CARD FOREIGN KEY (card_id) REFERENCES cards (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE project_views ADD CONSTRAINT FK_PROJECT_VIEWS_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE project_views ADD CONSTRAINT FK_PROJECT_VIEWS_PROJECT FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project_views DROP CONSTRAINT FK_PROJECT_VIEWS_PROJECT');
        $this->addSql('ALTER TABLE project_views DROP CONSTRAINT FK_PROJECT_VIEWS_USER');
        $this->addSql('ALTER TABLE card_views DROP CONSTRAINT FK_CARD_VIEWS_CARD');
        $this->addSql('ALTER TABLE card_views DROP CONSTRAINT FK_CARD_VIEWS_USER');
        $this->addSql('DROP TABLE project_views');
        $this->addSql('DROP TABLE card_views');
    }
}

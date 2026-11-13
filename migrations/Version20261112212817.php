<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261112212817 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE boards (id UUID NOT NULL, project_id UUID NOT NULL, name VARCHAR(100) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE card_activities (id UUID NOT NULL, card_id UUID NOT NULL, actor_id UUID DEFAULT NULL, type VARCHAR(50) NOT NULL, payload JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE cards (id UUID NOT NULL, column_id UUID NOT NULL, title VARCHAR(255) NOT NULL, description TEXT NOT NULL, position INT NOT NULL, due_date DATE DEFAULT NULL, creator_id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, archived_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE card_labels (card_id UUID NOT NULL, label_id UUID NOT NULL, PRIMARY KEY (card_id, label_id))');
        $this->addSql('CREATE INDEX IDX_C7EE51654ACC9A20 ON card_labels (card_id)');
        $this->addSql('CREATE INDEX IDX_C7EE516533B92F39 ON card_labels (label_id)');
        $this->addSql('CREATE TABLE card_assignees (card_id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (card_id, user_id))');
        $this->addSql('CREATE INDEX IDX_66CCFD884ACC9A20 ON card_assignees (card_id)');
        $this->addSql('CREATE INDEX IDX_66CCFD88A76ED395 ON card_assignees (user_id)');
        $this->addSql('CREATE TABLE columns (id UUID NOT NULL, board_id UUID NOT NULL, name VARCHAR(100) NOT NULL, position INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE comments (id UUID NOT NULL, card_id UUID NOT NULL, author_id UUID NOT NULL, body TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE labels (id UUID NOT NULL, board_id UUID NOT NULL, name VARCHAR(50) NOT NULL, color VARCHAR(7) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE project_members (id UUID NOT NULL, project_id UUID NOT NULL, user_id UUID NOT NULL, role VARCHAR(20) NOT NULL, joined_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_project_member ON project_members (project_id, user_id)');
        $this->addSql('CREATE TABLE projects (id UUID NOT NULL, name VARCHAR(100) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, archived_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE users (id UUID NOT NULL, email VARCHAR(180) NOT NULL, password_hash VARCHAR(255) NOT NULL, display_name VARCHAR(100) NOT NULL, is_global_admin BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
        $this->addSql('ALTER TABLE card_labels ADD CONSTRAINT FK_C7EE51654ACC9A20 FOREIGN KEY (card_id) REFERENCES cards (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE card_labels ADD CONSTRAINT FK_C7EE516533B92F39 FOREIGN KEY (label_id) REFERENCES labels (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE card_assignees ADD CONSTRAINT FK_66CCFD884ACC9A20 FOREIGN KEY (card_id) REFERENCES cards (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE card_assignees ADD CONSTRAINT FK_66CCFD88A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        // Manual FKs — Doctrine cannot infer them from plain uuid columns.
        $this->addSql('ALTER TABLE boards ADD CONSTRAINT FK_BOARDS_PROJECT FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE columns ADD CONSTRAINT FK_COLUMNS_BOARD FOREIGN KEY (board_id) REFERENCES boards (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE cards ADD CONSTRAINT FK_CARDS_COLUMN FOREIGN KEY (column_id) REFERENCES columns (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE cards ADD CONSTRAINT FK_CARDS_CREATOR FOREIGN KEY (creator_id) REFERENCES users (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE labels ADD CONSTRAINT FK_LABELS_BOARD FOREIGN KEY (board_id) REFERENCES boards (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE comments ADD CONSTRAINT FK_COMMENTS_CARD FOREIGN KEY (card_id) REFERENCES cards (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE comments ADD CONSTRAINT FK_COMMENTS_AUTHOR FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE project_members ADD CONSTRAINT FK_PROJECT_MEMBERS_PROJECT FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE project_members ADD CONSTRAINT FK_PROJECT_MEMBERS_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE card_activities ADD CONSTRAINT FK_CARD_ACTIVITIES_CARD FOREIGN KEY (card_id) REFERENCES cards (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE card_activities ADD CONSTRAINT FK_CARD_ACTIVITIES_ACTOR FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE card_labels DROP CONSTRAINT FK_C7EE51654ACC9A20');
        $this->addSql('ALTER TABLE card_labels DROP CONSTRAINT FK_C7EE516533B92F39');
        $this->addSql('ALTER TABLE card_assignees DROP CONSTRAINT FK_66CCFD884ACC9A20');
        $this->addSql('ALTER TABLE card_assignees DROP CONSTRAINT FK_66CCFD88A76ED395');
        $this->addSql('ALTER TABLE card_activities DROP CONSTRAINT FK_CARD_ACTIVITIES_CARD');
        $this->addSql('ALTER TABLE card_activities DROP CONSTRAINT FK_CARD_ACTIVITIES_ACTOR');
        $this->addSql('ALTER TABLE comments DROP CONSTRAINT FK_COMMENTS_CARD');
        $this->addSql('ALTER TABLE comments DROP CONSTRAINT FK_COMMENTS_AUTHOR');
        $this->addSql('ALTER TABLE cards DROP CONSTRAINT FK_CARDS_COLUMN');
        $this->addSql('ALTER TABLE cards DROP CONSTRAINT FK_CARDS_CREATOR');
        $this->addSql('ALTER TABLE columns DROP CONSTRAINT FK_COLUMNS_BOARD');
        $this->addSql('ALTER TABLE labels DROP CONSTRAINT FK_LABELS_BOARD');
        $this->addSql('ALTER TABLE boards DROP CONSTRAINT FK_BOARDS_PROJECT');
        $this->addSql('ALTER TABLE project_members DROP CONSTRAINT FK_PROJECT_MEMBERS_PROJECT');
        $this->addSql('ALTER TABLE project_members DROP CONSTRAINT FK_PROJECT_MEMBERS_USER');
        $this->addSql('DROP TABLE boards');
        $this->addSql('DROP TABLE card_activities');
        $this->addSql('DROP TABLE cards');
        $this->addSql('DROP TABLE card_labels');
        $this->addSql('DROP TABLE card_assignees');
        $this->addSql('DROP TABLE columns');
        $this->addSql('DROP TABLE comments');
        $this->addSql('DROP TABLE labels');
        $this->addSql('DROP TABLE project_members');
        $this->addSql('DROP TABLE projects');
        $this->addSql('DROP TABLE users');
    }
}

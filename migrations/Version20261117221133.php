<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261117221133 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Require board_attachments.created_at and add its foreign keys';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE board_attachments ALTER created_at SET NOT NULL');
        // Manual FKs — Doctrine cannot infer them from plain uuid columns.
        $this->addSql('ALTER TABLE board_attachments ADD CONSTRAINT FK_BOARD_ATTACHMENTS_CARD FOREIGN KEY (card_id) REFERENCES cards (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE board_attachments ADD CONSTRAINT FK_BOARD_ATTACHMENTS_COMMENT FOREIGN KEY (comment_id) REFERENCES comments (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE board_attachments DROP CONSTRAINT FK_BOARD_ATTACHMENTS_COMMENT');
        $this->addSql('ALTER TABLE board_attachments DROP CONSTRAINT FK_BOARD_ATTACHMENTS_CARD');
        $this->addSql('ALTER TABLE board_attachments ALTER created_at DROP NOT NULL');
    }
}

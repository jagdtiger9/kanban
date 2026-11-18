<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261117181634 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the board_attachments table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE board_attachments (id UUID NOT NULL, card_id UUID NOT NULL, comment_id UUID DEFAULT NULL, uploader_id UUID NOT NULL, original_name VARCHAR(255) NOT NULL, storage_key VARCHAR(255) NOT NULL, thumbnail_storage_key VARCHAR(255) DEFAULT NULL, mime_type VARCHAR(127) NOT NULL, size_bytes BIGINT NOT NULL, width INT DEFAULT NULL, height INT DEFAULT NULL, created_at TIMESTAMP(6) WITH TIME ZONE, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_85BF0BA5111795A5 ON board_attachments (storage_key)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_85BF0BA55CA9667B ON board_attachments (thumbnail_storage_key)');
        $this->addSql('CREATE INDEX board_attachments_card_idx ON board_attachments (card_id)');
        $this->addSql('CREATE INDEX board_attachments_comment_idx ON board_attachments (comment_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE board_attachments');
    }
}

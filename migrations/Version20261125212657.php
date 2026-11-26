<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261125212657 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cards.recurrence and card_occurrences table for recurring task cards';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cards ADD recurrence JSON DEFAULT NULL');
        $this->addSql(
            'CREATE TABLE card_occurrences (id UUID NOT NULL, card_id UUID NOT NULL, period DATE NOT NULL, completed_by UUID DEFAULT NULL, completed_at TIMESTAMP(6) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))'
        );
        $this->addSql('CREATE UNIQUE INDEX uniq_card_occurrences_card_period ON card_occurrences (card_id, period)');
        $this->addSql('CREATE INDEX idx_card_occurrences_completed_by ON card_occurrences (completed_by)');
        // Manual FKs — Doctrine cannot infer them from plain uuid columns.
        $this->addSql('ALTER TABLE card_occurrences ADD CONSTRAINT FK_CARD_OCCURRENCES_CARD FOREIGN KEY (card_id) REFERENCES cards (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE card_occurrences ADD CONSTRAINT FK_CARD_OCCURRENCES_COMPLETED_BY FOREIGN KEY (completed_by) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE card_occurrences');
        $this->addSql('ALTER TABLE cards DROP recurrence');
    }
}

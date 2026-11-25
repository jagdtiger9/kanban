<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261125163624 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cards.linked_card_id for card-to-card links';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cards ADD linked_card_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE cards ADD CONSTRAINT FK_CARDS_LINKED_CARD FOREIGN KEY (linked_card_id) REFERENCES cards (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cards DROP CONSTRAINT FK_CARDS_LINKED_CARD');
        $this->addSql('ALTER TABLE cards DROP linked_card_id');
    }
}

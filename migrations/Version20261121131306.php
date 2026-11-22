<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261121131306 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add indexes for board, card, comment, label, activity and membership lookups';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_boards_project_id ON boards (project_id)');
        $this->addSql('CREATE INDEX idx_columns_board_position ON columns (board_id, position)');
        $this->addSql('CREATE INDEX idx_cards_column_position ON cards (column_id, position)');
        $this->addSql('CREATE INDEX idx_labels_board_created ON labels (board_id, created_at)');
        $this->addSql('CREATE INDEX idx_comments_card_created ON comments (card_id, created_at, id)');
        $this->addSql('CREATE INDEX idx_card_activities_created_at ON card_activities (created_at, id)');
        $this->addSql('CREATE INDEX idx_card_activities_card_created ON card_activities (card_id, created_at, id)');
        $this->addSql('CREATE INDEX idx_project_members_user_id ON project_members (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_project_members_user_id');
        $this->addSql('DROP INDEX idx_card_activities_card_created');
        $this->addSql('DROP INDEX idx_card_activities_created_at');
        $this->addSql('DROP INDEX idx_comments_card_created');
        $this->addSql('DROP INDEX idx_labels_board_created');
        $this->addSql('DROP INDEX idx_cards_column_position');
        $this->addSql('DROP INDEX idx_columns_board_position');
        $this->addSql('DROP INDEX idx_boards_project_id');
    }
}

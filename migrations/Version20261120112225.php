<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120112225 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user_events table for the per-user event feed';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE user_events (id UUID NOT NULL, user_id UUID NOT NULL, type VARCHAR(40) NOT NULL, aggregate_root_id UUID NOT NULL, project_id UUID DEFAULT NULL, card_id UUID DEFAULT NULL, actor_id UUID DEFAULT NULL, payload JSON NOT NULL, occurred_on TIMESTAMP(6) WITH TIME ZONE NOT NULL, created_at TIMESTAMP(6) WITH TIME ZONE NOT NULL, seen_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL, PRIMARY KEY (id))'
        );
        $this->addSql('CREATE INDEX idx_user_events_list ON user_events (user_id, occurred_on, id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_events_recipient_event ON user_events (user_id, type, aggregate_root_id, occurred_on)');
        $this->addSql('CREATE INDEX idx_user_events_unread ON user_events (user_id) WHERE seen_at IS NULL');
        $this->addSql('CREATE INDEX idx_user_events_project_id ON user_events (project_id)');
        $this->addSql('CREATE INDEX idx_user_events_card_id ON user_events (card_id)');
        $this->addSql('ALTER TABLE user_events ADD CONSTRAINT FK_USER_EVENTS_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE user_events ADD CONSTRAINT FK_USER_EVENTS_PROJECT FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE user_events ADD CONSTRAINT FK_USER_EVENTS_CARD FOREIGN KEY (card_id) REFERENCES cards (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE user_events ADD CONSTRAINT FK_USER_EVENTS_ACTOR FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_events');
    }
}

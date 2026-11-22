<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261121194419 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add exclusive card executor (cards.executor_id)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cards ADD executor_id UUID DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_CARDS_EXECUTOR ON cards (executor_id)');
        $this->addSql('ALTER TABLE cards ADD CONSTRAINT FK_CARDS_EXECUTOR FOREIGN KEY (executor_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cards DROP CONSTRAINT FK_CARDS_EXECUTOR');
        $this->addSql('DROP INDEX IDX_CARDS_EXECUTOR');
        $this->addSql('ALTER TABLE cards DROP executor_id');
    }
}

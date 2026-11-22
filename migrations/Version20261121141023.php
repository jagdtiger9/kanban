<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261121141023 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add avatar color to users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD avatar_color VARCHAR(7) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP avatar_color');
    }
}

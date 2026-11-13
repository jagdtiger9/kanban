<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261112214346 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Timestamp columns to timestamptz with microsecond precision';
    }

    public function up(Schema $schema): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $this->addSql(sprintf(
                    'ALTER TABLE %s ALTER COLUMN %s TYPE TIMESTAMP(6) WITH TIME ZONE',
                    $table,
                    $column,
                ));
            }
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $this->addSql(sprintf(
                    'ALTER TABLE %s ALTER COLUMN %s TYPE TIMESTAMP(0)',
                    $table,
                    $column,
                ));
            }
        }
    }

    private const COLUMNS = [
        'users' => ['created_at'],
        'projects' => ['created_at', 'archived_at'],
        'project_members' => ['joined_at'],
        'boards' => ['created_at'],
        'columns' => ['created_at'],
        'cards' => ['created_at', 'updated_at', 'archived_at'],
        'labels' => ['created_at'],
        'comments' => ['created_at', 'updated_at'],
        'card_activities' => ['created_at'],
    ];
}

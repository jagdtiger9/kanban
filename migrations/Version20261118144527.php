<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261118144527 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add news_posts table and users.is_news_maker';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE news_posts (id UUID NOT NULL, author_id UUID NOT NULL, body TEXT NOT NULL, created_at TIMESTAMP(6) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(6) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))'
        );
        $this->addSql('CREATE INDEX idx_news_posts_created_at ON news_posts (created_at)');
        // Manual FK — Doctrine cannot infer it from a plain uuid column.
        $this->addSql('ALTER TABLE news_posts ADD CONSTRAINT FK_NEWS_POSTS_AUTHOR FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE users ADD is_news_maker BOOLEAN DEFAULT false NOT NULL');
        // The entity maps no default; the temporary default only backfills existing rows.
        $this->addSql('ALTER TABLE users ALTER is_news_maker DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP is_news_maker');
        $this->addSql('ALTER TABLE news_posts DROP CONSTRAINT FK_NEWS_POSTS_AUTHOR');
        $this->addSql('DROP TABLE news_posts');
    }
}

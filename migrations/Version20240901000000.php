<?php

declare(strict_types=1);

// phpcs:ignoreFile
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20240901000000 extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return 'Register scheduled commands for blog cache refresh.';
    }

    #[Override]
    public function isTransactional(): bool
    {
        return false;
    }

    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
INSERT INTO scheduled_command (name, command, arguments, cron_expression, priority, execute_immediately, disabled, locked, ping_back_url, ping_back_failed_url, notes, version, created_at)
VALUES
    ('blog_cache_refresh_posts', 'blog:cache:refresh', '--scope=posts', '*/5 * * * *', 0, 0, 0, 0, NULL, NULL, 'Refreshes the public blog feed cache', 1, NOW()),
    ('blog_cache_refresh_comments', 'blog:cache:refresh', '--scope=comments', '*/5 * * * *', 0, 0, 0, 0, NULL, NULL, 'Refreshes blog comment caches', 1, NOW()),
    ('blog_cache_refresh_reactions', 'blog:cache:refresh', '--scope=reactions', '*/5 * * * *', 0, 0, 0, 0, NULL, NULL, 'Refreshes blog likes and reactions caches', 1, NOW())
ON DUPLICATE KEY UPDATE
    command = VALUES(command),
    arguments = VALUES(arguments),
    cron_expression = VALUES(cron_expression),
    priority = VALUES(priority),
    execute_immediately = VALUES(execute_immediately),
    disabled = VALUES(disabled),
    locked = VALUES(locked),
    notes = VALUES(notes)
SQL
        );
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
DELETE FROM scheduled_command
WHERE name IN ('blog_cache_refresh_posts', 'blog_cache_refresh_comments', 'blog_cache_refresh_reactions')
SQL
        );
    }
}

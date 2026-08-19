<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260819130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add metrics.read and grant it to the system roles that may read metrics.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'This migration requires PostgreSQL.');

        $this->addSql("INSERT INTO permissions (id, code, name, description, category, created_at, updated_at) VALUES ('00000000-0000-0000-0000-000000000026', 'metrics.read', 'Read metrics', 'Read VictoriaMetrics-backed metric values through the Osira API.', 'Monitoring', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
        $this->addSql("INSERT INTO role_permissions (role_id, permission_id) VALUES ('00000000-0000-0000-0000-000000000101', '00000000-0000-0000-0000-000000000026')");
        $this->addSql("INSERT INTO role_permissions (role_id, permission_id) VALUES ('00000000-0000-0000-0000-000000000102', '00000000-0000-0000-0000-000000000026')");
        $this->addSql("INSERT INTO role_permissions (role_id, permission_id) VALUES ('00000000-0000-0000-0000-000000000103', '00000000-0000-0000-0000-000000000026')");
        $this->addSql("INSERT INTO role_permissions (role_id, permission_id) VALUES ('00000000-0000-0000-0000-000000000104', '00000000-0000-0000-0000-000000000026')");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'This migration requires PostgreSQL.');

        $this->addSql("DELETE FROM role_permissions WHERE permission_id = '00000000-0000-0000-0000-000000000026'");
        $this->addSql("DELETE FROM permissions WHERE id = '00000000-0000-0000-0000-000000000026'");
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260825143000 extends AbstractMigration
{
    /** @var array<string, string> */
    private const array PERMISSIONS = [
        '00000000-0000-0000-0000-000000000030' => 'maintenance_windows.read|Read maintenance windows|View planned node maintenance windows.|Maintenance',
        '00000000-0000-0000-0000-000000000031' => 'maintenance_windows.create|Create maintenance windows|Create planned node maintenance windows.|Maintenance',
        '00000000-0000-0000-0000-000000000032' => 'maintenance_windows.update|Update maintenance windows|Update planned node maintenance windows and target scopes.|Maintenance',
        '00000000-0000-0000-0000-000000000033' => 'maintenance_windows.delete|Delete maintenance windows|Delete planned node maintenance windows.|Maintenance',
    ];

    public function getDescription(): string
    {
        return 'Add maintenance windows, scoped assignments, audit, and RBAC permissions.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'This migration requires PostgreSQL.');

        $this->addSql('CREATE TABLE maintenance_windows (id UUID NOT NULL, name VARCHAR(128) NOT NULL, description TEXT DEFAULT NULL, starts_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ends_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, is_enabled BOOLEAN DEFAULT true NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_maintenance_windows_active_period ON maintenance_windows (is_enabled, starts_at, ends_at)');
        $this->addSql('ALTER TABLE maintenance_windows ADD CONSTRAINT chk_maintenance_windows_period CHECK (ends_at > starts_at)');

        $this->addSql('CREATE TABLE maintenance_window_nodes (maintenance_window_id UUID NOT NULL, node_id UUID NOT NULL, PRIMARY KEY (maintenance_window_id, node_id))');
        $this->addSql('CREATE INDEX IDX_8044E711C4C83B3C ON maintenance_window_nodes (maintenance_window_id)');
        $this->addSql('CREATE INDEX IDX_8044E711460D9FD7 ON maintenance_window_nodes (node_id)');
        $this->addSql('CREATE TABLE maintenance_window_node_groups (maintenance_window_id UUID NOT NULL, node_group_id UUID NOT NULL, PRIMARY KEY (maintenance_window_id, node_group_id))');
        $this->addSql('CREATE INDEX IDX_5F4D7483C4C83B3C ON maintenance_window_node_groups (maintenance_window_id)');
        $this->addSql('CREATE INDEX IDX_5F4D748340F9C112 ON maintenance_window_node_groups (node_group_id)');

        $this->addSql('ALTER TABLE maintenance_window_nodes ADD CONSTRAINT FK_8044E711C4C83B3C FOREIGN KEY (maintenance_window_id) REFERENCES maintenance_windows (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE maintenance_window_nodes ADD CONSTRAINT FK_8044E711460D9FD7 FOREIGN KEY (node_id) REFERENCES nodes (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE maintenance_window_node_groups ADD CONSTRAINT FK_5F4D7483C4C83B3C FOREIGN KEY (maintenance_window_id) REFERENCES maintenance_windows (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE maintenance_window_node_groups ADD CONSTRAINT FK_5F4D748340F9C112 FOREIGN KEY (node_group_id) REFERENCES node_groups (id) ON DELETE CASCADE NOT DEFERRABLE');

        $this->createAuditTable('audit_maintenance_windows');

        foreach (self::PERMISSIONS as $id => $definition) {
            [$code, $name, $description, $category] = explode('|', $definition, 4);
            $this->addSql(\sprintf(
                "INSERT INTO permissions (id, code, name, description, category, created_at, updated_at) VALUES ('%s', '%s', '%s', '%s', '%s', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
                $id,
                $code,
                str_replace("'", "''", $name),
                str_replace("'", "''", $description),
                $category,
            ));
        }

        foreach (array_keys(self::PERMISSIONS) as $id) {
            $this->addSql(\sprintf("INSERT INTO role_permissions (role_id, permission_id) VALUES ('00000000-0000-0000-0000-000000000101', '%s')", $id));
            $this->addSql(\sprintf("INSERT INTO role_permissions (role_id, permission_id) VALUES ('00000000-0000-0000-0000-000000000102', '%s')", $id));
        }
        foreach ([
            '00000000-0000-0000-0000-000000000030',
            '00000000-0000-0000-0000-000000000031',
            '00000000-0000-0000-0000-000000000032',
        ] as $id) {
            $this->addSql(\sprintf("INSERT INTO role_permissions (role_id, permission_id) VALUES ('00000000-0000-0000-0000-000000000103', '%s')", $id));
        }
        $this->addSql("INSERT INTO role_permissions (role_id, permission_id) VALUES ('00000000-0000-0000-0000-000000000104', '00000000-0000-0000-0000-000000000030')");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'This migration requires PostgreSQL.');

        foreach (array_keys(self::PERMISSIONS) as $id) {
            $this->addSql("DELETE FROM role_permissions WHERE permission_id = '".$id."'");
            $this->addSql("DELETE FROM permissions WHERE id = '".$id."'");
        }

        $this->addSql('DROP TABLE audit_maintenance_windows');
        $this->addSql('DROP TABLE maintenance_window_node_groups');
        $this->addSql('DROP TABLE maintenance_window_nodes');
        $this->addSql('DROP TABLE maintenance_windows');
    }

    private function createAuditTable(string $table): void
    {
        $this->addSql(\sprintf('CREATE TABLE %s (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, type VARCHAR(10) NOT NULL, object_id VARCHAR(255) NOT NULL, discriminator VARCHAR(255) DEFAULT NULL, transaction_hash VARCHAR(40) DEFAULT NULL, diffs JSONB DEFAULT NULL, extra_data JSONB DEFAULT NULL, blame_id VARCHAR(255) DEFAULT NULL, blame_user VARCHAR(255) DEFAULT NULL, blame_user_fqdn VARCHAR(255) DEFAULT NULL, blame_user_firewall VARCHAR(100) DEFAULT NULL, ip VARCHAR(45) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))', $table));

        $hash = md5($table);
        foreach (['type', 'object_id', 'discriminator', 'transaction_hash', 'blame_id', 'created_at'] as $column) {
            $this->addSql(\sprintf('CREATE INDEX %s_%s_idx ON %s (%s)', $column, $hash, $table, $column));
        }
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260819110000 extends AbstractMigration
{
    private const array AUDIT_TABLES = [
        'audit_monitoring_templates',
        'audit_item_definitions',
    ];

    /** @var array<string, string> */
    private const array PERMISSIONS = [
        '00000000-0000-0000-0000-000000000018' => 'monitoring_templates.read|Read monitoring templates|View monitoring templates and their assignments.|Monitoring',
        '00000000-0000-0000-0000-000000000019' => 'monitoring_templates.create|Create monitoring templates|Create monitoring templates.|Monitoring',
        '00000000-0000-0000-0000-000000000020' => 'monitoring_templates.update|Update monitoring templates|Update monitoring templates and their item assignments.|Monitoring',
        '00000000-0000-0000-0000-000000000021' => 'monitoring_templates.delete|Delete monitoring templates|Delete non-system monitoring templates.|Monitoring',
        '00000000-0000-0000-0000-000000000022' => 'item_definitions.read|Read item definitions|View item definitions.|Monitoring',
        '00000000-0000-0000-0000-000000000023' => 'item_definitions.create|Create item definitions|Create item definitions.|Monitoring',
        '00000000-0000-0000-0000-000000000024' => 'item_definitions.update|Update item definitions|Update item definitions.|Monitoring',
        '00000000-0000-0000-0000-000000000025' => 'item_definitions.delete|Delete item definitions|Delete non-system item definitions.|Monitoring',
    ];

    public function getDescription(): string
    {
        return 'Add monitoring templates, item definitions, monitoring assignments, audit tables, and RBAC permissions.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'This migration requires PostgreSQL.');

        $this->addSql('CREATE TABLE monitoring_templates (id UUID NOT NULL, name VARCHAR(128) NOT NULL, slug VARCHAR(128) NOT NULL, description TEXT DEFAULT NULL, is_system BOOLEAN DEFAULT FALSE NOT NULL, is_enabled BOOLEAN DEFAULT TRUE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_monitoring_templates_name ON monitoring_templates (name)');
        $this->addSql('CREATE UNIQUE INDEX uniq_monitoring_templates_slug ON monitoring_templates (slug)');

        $this->addSql('CREATE TABLE item_definitions (id UUID NOT NULL, key_name VARCHAR(128) NOT NULL, name VARCHAR(128) NOT NULL, description TEXT DEFAULT NULL, category VARCHAR(128) DEFAULT NULL, unit VARCHAR(32) DEFAULT NULL, value_type VARCHAR(16) NOT NULL, interval_seconds INT NOT NULL, timeout_seconds INT DEFAULT NULL, is_system BOOLEAN DEFAULT FALSE NOT NULL, is_enabled BOOLEAN DEFAULT TRUE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_item_definitions_key ON item_definitions (key_name)');

        $this->addSql('CREATE TABLE monitoring_template_item_definitions (monitoring_template_id UUID NOT NULL, item_definition_id UUID NOT NULL, PRIMARY KEY(monitoring_template_id, item_definition_id))');
        $this->addSql('CREATE INDEX idx_mt_items_template ON monitoring_template_item_definitions (monitoring_template_id)');
        $this->addSql('CREATE INDEX idx_mt_items_item ON monitoring_template_item_definitions (item_definition_id)');

        $this->addSql('CREATE TABLE node_monitoring_templates (node_id UUID NOT NULL, monitoring_template_id UUID NOT NULL, PRIMARY KEY(node_id, monitoring_template_id))');
        $this->addSql('CREATE INDEX idx_node_monitoring_template_node ON node_monitoring_templates (node_id)');
        $this->addSql('CREATE INDEX idx_node_monitoring_template_template ON node_monitoring_templates (monitoring_template_id)');

        $this->addSql('CREATE TABLE node_group_monitoring_templates (node_group_id UUID NOT NULL, monitoring_template_id UUID NOT NULL, PRIMARY KEY(node_group_id, monitoring_template_id))');
        $this->addSql('CREATE INDEX idx_node_group_monitoring_template_group ON node_group_monitoring_templates (node_group_id)');
        $this->addSql('CREATE INDEX idx_node_group_monitoring_template_template ON node_group_monitoring_templates (monitoring_template_id)');

        $this->addSql('ALTER TABLE monitoring_template_item_definitions ADD CONSTRAINT fk_mt_items_template FOREIGN KEY (monitoring_template_id) REFERENCES monitoring_templates (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE monitoring_template_item_definitions ADD CONSTRAINT fk_mt_items_item FOREIGN KEY (item_definition_id) REFERENCES item_definitions (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE node_monitoring_templates ADD CONSTRAINT fk_node_monitoring_template_node FOREIGN KEY (node_id) REFERENCES nodes (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE node_monitoring_templates ADD CONSTRAINT fk_node_monitoring_template_template FOREIGN KEY (monitoring_template_id) REFERENCES monitoring_templates (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE node_group_monitoring_templates ADD CONSTRAINT fk_node_group_monitoring_template_group FOREIGN KEY (node_group_id) REFERENCES node_groups (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE node_group_monitoring_templates ADD CONSTRAINT fk_node_group_monitoring_template_template FOREIGN KEY (monitoring_template_id) REFERENCES monitoring_templates (id) ON DELETE CASCADE NOT DEFERRABLE');

        foreach (self::AUDIT_TABLES as $table) {
            $this->createAuditTable($table);
        }

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
            $this->addSql(\sprintf("INSERT INTO role_permissions (role_id, permission_id) VALUES ('00000000-0000-0000-0000-000000000101', '%s')", $id));
            $this->addSql(\sprintf("INSERT INTO role_permissions (role_id, permission_id) VALUES ('00000000-0000-0000-0000-000000000102', '%s')", $id));
        }
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'This migration requires PostgreSQL.');

        foreach (array_keys(self::PERMISSIONS) as $id) {
            $this->addSql("DELETE FROM role_permissions WHERE permission_id = '".$id."'");
            $this->addSql("DELETE FROM permissions WHERE id = '".$id."'");
        }

        foreach (array_reverse(self::AUDIT_TABLES) as $table) {
            $this->addSql('DROP TABLE '.$table);
        }

        $this->addSql('DROP TABLE node_group_monitoring_templates');
        $this->addSql('DROP TABLE node_monitoring_templates');
        $this->addSql('DROP TABLE monitoring_template_item_definitions');
        $this->addSql('DROP TABLE monitoring_templates');
        $this->addSql('DROP TABLE item_definitions');
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

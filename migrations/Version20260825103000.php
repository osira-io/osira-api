<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260825103000 extends AbstractMigration
{
    private const string MANAGE_COMMANDS_PERMISSION_ID = '00000000-0000-0000-0000-000000000029';

    public function getDescription(): string
    {
        return 'Replace the system monitoring catalog with OS-specific custom commands and remove direct node/template assignments.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'This migration requires PostgreSQL.');
        $this->addSql('DELETE FROM incidents WHERE alert_rule_id IN (SELECT id FROM alert_rules WHERE item_definition_id IN (SELECT id FROM item_definitions WHERE is_system = TRUE))');
        $this->addSql('DELETE FROM alert_rules WHERE item_definition_id IN (SELECT id FROM item_definitions WHERE is_system = TRUE)');
        $this->addSql('DELETE FROM monitoring_templates WHERE is_system = TRUE');
        $this->addSql('DELETE FROM item_definitions WHERE is_system = TRUE');
        $this->addSql('ALTER TABLE item_definitions ADD linux_command TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE item_definitions ADD windows_command TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE item_definitions DROP category');
        $this->addSql('ALTER TABLE item_definitions DROP is_system');
        $this->addSql('ALTER TABLE monitoring_templates DROP is_system');
        $this->addSql('DROP TABLE node_monitoring_templates');
        $this->addSql("INSERT INTO permissions (id, code, name, description, category, created_at, updated_at) VALUES ('".self::MANAGE_COMMANDS_PERMISSION_ID."', 'item_definitions.manage_commands', 'Manage item commands', 'Create or modify Bash and PowerShell collection commands.', 'Monitoring', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
        $this->addSql("UPDATE permissions SET description = 'Delete monitoring templates.', updated_at = CURRENT_TIMESTAMP WHERE code = 'monitoring_templates.delete'");
        $this->addSql("UPDATE permissions SET description = 'Delete item definitions.', updated_at = CURRENT_TIMESTAMP WHERE code = 'item_definitions.delete'");
        foreach (['101', '102'] as $roleSuffix) {
            $this->addSql("INSERT INTO role_permissions (role_id, permission_id) VALUES ('00000000-0000-0000-0000-000000000".$roleSuffix."', '".self::MANAGE_COMMANDS_PERMISSION_ID."')");
        }
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'This migration requires PostgreSQL.');
        $this->addSql("DELETE FROM role_permissions WHERE permission_id = '".self::MANAGE_COMMANDS_PERMISSION_ID."'");
        $this->addSql("DELETE FROM permissions WHERE id = '".self::MANAGE_COMMANDS_PERMISSION_ID."'");
        $this->addSql("UPDATE permissions SET description = 'Delete non-system monitoring templates.', updated_at = CURRENT_TIMESTAMP WHERE code = 'monitoring_templates.delete'");
        $this->addSql("UPDATE permissions SET description = 'Delete non-system item definitions.', updated_at = CURRENT_TIMESTAMP WHERE code = 'item_definitions.delete'");
        $this->addSql('ALTER TABLE item_definitions DROP linux_command');
        $this->addSql('ALTER TABLE item_definitions DROP windows_command');
        $this->addSql('ALTER TABLE item_definitions ADD category VARCHAR(128) DEFAULT NULL');
        $this->addSql('ALTER TABLE item_definitions ADD is_system BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql('ALTER TABLE monitoring_templates ADD is_system BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql('CREATE TABLE node_monitoring_templates (node_id UUID NOT NULL, monitoring_template_id UUID NOT NULL, PRIMARY KEY(node_id, monitoring_template_id))');
        $this->addSql('CREATE INDEX IDX_D8FFACCF460D9FD7 ON node_monitoring_templates (node_id)');
        $this->addSql('CREATE INDEX IDX_D8FFACCFBB64145D ON node_monitoring_templates (monitoring_template_id)');
        $this->addSql('ALTER TABLE node_monitoring_templates ADD CONSTRAINT fk_node_monitoring_template_node FOREIGN KEY (node_id) REFERENCES nodes (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE node_monitoring_templates ADD CONSTRAINT fk_node_monitoring_template_template FOREIGN KEY (monitoring_template_id) REFERENCES monitoring_templates (id) ON DELETE CASCADE NOT DEFERRABLE');
    }
}

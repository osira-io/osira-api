<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260825200000 extends AbstractMigration
{
    /** @var array<string, string> */
    private const array PERMISSIONS = [
        '00000000-0000-0000-0000-000000000036' => 'notification_channels.read|Read notification channels|View notification channels without secret material.',
        '00000000-0000-0000-0000-000000000037' => 'notification_channels.create|Create notification channels|Create email and webhook notification channels.',
        '00000000-0000-0000-0000-000000000038' => 'notification_channels.update|Update notification channels|Update email and webhook notification channels.',
        '00000000-0000-0000-0000-000000000039' => 'notification_channels.delete|Delete notification channels|Delete notification channels.',
        '00000000-0000-0000-0000-000000000040' => 'notification_rules.read|Read notification rules|View incident notification routing rules.',
        '00000000-0000-0000-0000-000000000041' => 'notification_rules.create|Create notification rules|Create incident notification routing rules.',
        '00000000-0000-0000-0000-000000000042' => 'notification_rules.update|Update notification rules|Update incident notification routing rules.',
        '00000000-0000-0000-0000-000000000043' => 'notification_rules.delete|Delete notification rules|Delete incident notification routing rules.',
    ];

    public function getDescription(): string
    {
        return 'Add incident notification channels, routing rules, idempotent deliveries, Messenger queues, audit, and RBAC.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'This migration requires PostgreSQL.');

        $this->addSql('CREATE TABLE notification_channels (id UUID NOT NULL, name VARCHAR(128) NOT NULL, type VARCHAR(16) NOT NULL, is_enabled BOOLEAN DEFAULT true NOT NULL, email_recipients JSON NOT NULL, webhook_url VARCHAR(2048) DEFAULT NULL, webhook_secret_ciphertext TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE notification_channels ADD CONSTRAINT uniq_notification_channels_name UNIQUE (name)');
        $this->addSql('CREATE TABLE notification_rules (id UUID NOT NULL, name VARCHAR(128) NOT NULL, is_enabled BOOLEAN DEFAULT true NOT NULL, severities JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE notification_rules ADD CONSTRAINT uniq_notification_rules_name UNIQUE (name)');

        $this->addSql('CREATE TABLE notification_rule_channels (notification_rule_id UUID NOT NULL, notification_channel_id UUID NOT NULL, PRIMARY KEY (notification_rule_id, notification_channel_id))');
        $this->addSql('CREATE INDEX IDX_695ACBD3615089E6 ON notification_rule_channels (notification_rule_id)');
        $this->addSql('CREATE INDEX IDX_695ACBD389870488 ON notification_rule_channels (notification_channel_id)');
        $this->addSql('ALTER TABLE notification_rule_channels ADD CONSTRAINT FK_7EC9B534DD3CD3F5 FOREIGN KEY (notification_rule_id) REFERENCES notification_rules (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE notification_rule_channels ADD CONSTRAINT FK_7EC9B534C3E6CB6F FOREIGN KEY (notification_channel_id) REFERENCES notification_channels (id) ON DELETE CASCADE NOT DEFERRABLE');

        $this->addSql('CREATE TABLE notification_rule_nodes (notification_rule_id UUID NOT NULL, node_id UUID NOT NULL, PRIMARY KEY (notification_rule_id, node_id))');
        $this->addSql('CREATE INDEX IDX_287D801E615089E6 ON notification_rule_nodes (notification_rule_id)');
        $this->addSql('CREATE INDEX IDX_287D801E460D9FD7 ON notification_rule_nodes (node_id)');
        $this->addSql('ALTER TABLE notification_rule_nodes ADD CONSTRAINT FK_71B08192DD3CD3F5 FOREIGN KEY (notification_rule_id) REFERENCES notification_rules (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE notification_rule_nodes ADD CONSTRAINT FK_71B08192460D9FD7 FOREIGN KEY (node_id) REFERENCES nodes (id) ON DELETE CASCADE NOT DEFERRABLE');

        $this->addSql('CREATE TABLE notification_rule_node_groups (notification_rule_id UUID NOT NULL, node_group_id UUID NOT NULL, PRIMARY KEY (notification_rule_id, node_group_id))');
        $this->addSql('CREATE INDEX IDX_DF41D6F8615089E6 ON notification_rule_node_groups (notification_rule_id)');
        $this->addSql('CREATE INDEX IDX_DF41D6F840F9C112 ON notification_rule_node_groups (node_group_id)');
        $this->addSql('ALTER TABLE notification_rule_node_groups ADD CONSTRAINT FK_49E596E1DD3CD3F5 FOREIGN KEY (notification_rule_id) REFERENCES notification_rules (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE notification_rule_node_groups ADD CONSTRAINT FK_49E596E140F9C112 FOREIGN KEY (node_group_id) REFERENCES node_groups (id) ON DELETE CASCADE NOT DEFERRABLE');

        $this->addSql('CREATE TABLE notification_deliveries (id UUID NOT NULL, incident_id UUID NOT NULL, channel_id UUID NOT NULL, event VARCHAR(32) NOT NULL, status VARCHAR(16) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_9475204A59E53FB9 ON notification_deliveries (incident_id)');
        $this->addSql('CREATE INDEX IDX_9475204A72F5A1AA ON notification_deliveries (channel_id)');
        $this->addSql('ALTER TABLE notification_deliveries ADD CONSTRAINT uniq_notification_delivery_event_channel UNIQUE (incident_id, event, channel_id)');
        $this->addSql('ALTER TABLE notification_deliveries ADD CONSTRAINT FK_2861A63D3A8B2A3F FOREIGN KEY (incident_id) REFERENCES incidents (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE notification_deliveries ADD CONSTRAINT FK_2861A63D72F5A1AA FOREIGN KEY (channel_id) REFERENCES notification_channels (id) ON DELETE RESTRICT NOT DEFERRABLE');

        $this->addSql('CREATE TABLE messenger_messages (id BIGSERIAL NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');

        $this->createAuditTable('audit_notification_channels');
        $this->createAuditTable('audit_notification_rules');

        foreach (self::PERMISSIONS as $id => $definition) {
            [$code, $name, $description] = explode('|', $definition, 3);
            $this->addSql(\sprintf("INSERT INTO permissions (id, code, name, description, category, created_at, updated_at) VALUES ('%s', '%s', '%s', '%s', 'Notifications', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)", $id, $code, $name, $description));
            foreach (['00000000-0000-0000-0000-000000000101', '00000000-0000-0000-0000-000000000102'] as $roleId) {
                $this->addSql(\sprintf("INSERT INTO role_permissions (role_id, permission_id) VALUES ('%s', '%s')", $roleId, $id));
            }
        }
        foreach (['00000000-0000-0000-0000-000000000036', '00000000-0000-0000-0000-000000000040'] as $permissionId) {
            foreach (['00000000-0000-0000-0000-000000000103', '00000000-0000-0000-0000-000000000104'] as $roleId) {
                $this->addSql(\sprintf("INSERT INTO role_permissions (role_id, permission_id) VALUES ('%s', '%s')", $roleId, $permissionId));
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'This migration requires PostgreSQL.');
        foreach (array_keys(self::PERMISSIONS) as $id) {
            $this->addSql("DELETE FROM role_permissions WHERE permission_id = '".$id."'");
            $this->addSql("DELETE FROM permissions WHERE id = '".$id."'");
        }
        $this->addSql('DROP TABLE audit_notification_rules');
        $this->addSql('DROP TABLE audit_notification_channels');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('DROP TABLE notification_deliveries');
        $this->addSql('DROP TABLE notification_rule_node_groups');
        $this->addSql('DROP TABLE notification_rule_nodes');
        $this->addSql('DROP TABLE notification_rule_channels');
        $this->addSql('DROP TABLE notification_rules');
        $this->addSql('DROP TABLE notification_channels');
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

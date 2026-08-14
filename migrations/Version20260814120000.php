<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260814120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Pluralize application tables and add node business properties and node groups.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'This migration requires PostgreSQL.');

        $this->addSql('ALTER TABLE node RENAME TO nodes');
        $this->addSql('ALTER TABLE agent RENAME TO agents');
        $this->addSql('ALTER TABLE enrollment_token RENAME TO enrollment_tokens');
        $this->addSql('ALTER TABLE agent_credential RENAME TO agent_credentials');
        $this->addSql('ALTER TABLE app_user RENAME TO users');

        $this->addSql('ALTER INDEX node_pkey RENAME TO nodes_pkey');
        $this->addSql('ALTER INDEX agent_pkey RENAME TO agents_pkey');
        $this->addSql('ALTER INDEX enrollment_token_pkey RENAME TO enrollment_tokens_pkey');
        $this->addSql('ALTER INDEX agent_credential_pkey RENAME TO agent_credentials_pkey');
        $this->addSql('ALTER INDEX app_user_pkey RENAME TO users_pkey');
        $this->addSql('ALTER INDEX idx_agent_node RENAME TO idx_agents_node');
        $this->addSql('ALTER INDEX uniq_enrollment_token_hash RENAME TO uniq_enrollment_tokens_hash');
        $this->addSql('ALTER INDEX idx_agent_credential_agent RENAME TO idx_agent_credentials_agent');
        $this->addSql('ALTER INDEX uniq_user_email RENAME TO uniq_users_email');
        $this->addSql('ALTER TABLE agents RENAME CONSTRAINT fk_268b9c9d460d9fd7 TO FK_9596AB6E460D9FD7');
        $this->addSql('ALTER TABLE agent_credentials RENAME CONSTRAINT fk_7439cc443414710b TO FK_6ACB734E3414710B');

        $this->addSql("ALTER TABLE nodes ADD environment VARCHAR(64) DEFAULT NULL, ADD tags JSON NOT NULL DEFAULT '[]'");
        $this->addSql('ALTER TABLE nodes ALTER tags DROP DEFAULT');
        $this->addSql('CREATE INDEX idx_nodes_hostname ON nodes (hostname)');
        $this->addSql('CREATE INDEX idx_nodes_environment ON nodes (environment)');

        $this->addSql('CREATE TABLE node_groups (id UUID NOT NULL, name VARCHAR(128) NOT NULL, description TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_node_groups_name ON node_groups (name)');
        $this->addSql('CREATE TABLE node_group_nodes (node_id UUID NOT NULL, node_group_id UUID NOT NULL, PRIMARY KEY (node_id, node_group_id))');
        $this->addSql('CREATE INDEX IDX_D1A857240F9C112 ON node_group_nodes (node_group_id)');
        $this->addSql('ALTER TABLE node_group_nodes ADD CONSTRAINT FK_D1A8572460D9FD7 FOREIGN KEY (node_id) REFERENCES nodes (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE node_group_nodes ADD CONSTRAINT FK_D1A857240F9C112 FOREIGN KEY (node_group_id) REFERENCES node_groups (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'This migration requires PostgreSQL.');

        $this->addSql('DROP TABLE node_group_nodes');
        $this->addSql('DROP TABLE node_groups');
        $this->addSql('DROP INDEX idx_nodes_environment');
        $this->addSql('DROP INDEX idx_nodes_hostname');
        $this->addSql('ALTER TABLE nodes DROP environment, DROP tags');

        $this->addSql('ALTER TABLE agents RENAME CONSTRAINT fk_9596ab6e460d9fd7 TO FK_268B9C9D460D9FD7');
        $this->addSql('ALTER TABLE agent_credentials RENAME CONSTRAINT fk_6acb734e3414710b TO FK_7439CC443414710B');
        $this->addSql('ALTER INDEX idx_agents_node RENAME TO idx_agent_node');
        $this->addSql('ALTER INDEX uniq_enrollment_tokens_hash RENAME TO uniq_enrollment_token_hash');
        $this->addSql('ALTER INDEX idx_agent_credentials_agent RENAME TO idx_agent_credential_agent');
        $this->addSql('ALTER INDEX uniq_users_email RENAME TO uniq_user_email');
        $this->addSql('ALTER INDEX nodes_pkey RENAME TO node_pkey');
        $this->addSql('ALTER INDEX agents_pkey RENAME TO agent_pkey');
        $this->addSql('ALTER INDEX enrollment_tokens_pkey RENAME TO enrollment_token_pkey');
        $this->addSql('ALTER INDEX agent_credentials_pkey RENAME TO agent_credential_pkey');
        $this->addSql('ALTER INDEX users_pkey RENAME TO app_user_pkey');

        $this->addSql('ALTER TABLE nodes RENAME TO node');
        $this->addSql('ALTER TABLE agents RENAME TO agent');
        $this->addSql('ALTER TABLE enrollment_tokens RENAME TO enrollment_token');
        $this->addSql('ALTER TABLE agent_credentials RENAME TO agent_credential');
        $this->addSql('ALTER TABLE users RENAME TO app_user');
    }
}

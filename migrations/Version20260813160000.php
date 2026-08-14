<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260813160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create nodes, agents, enrollment tokens, and agent credentials.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'This migration requires PostgreSQL.');

        $this->addSql('CREATE TABLE node (id UUID NOT NULL, hostname VARCHAR(255) NOT NULL, display_name VARCHAR(255) DEFAULT NULL, os VARCHAR(64) NOT NULL, architecture VARCHAR(64) NOT NULL, first_seen_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE agent (id UUID NOT NULL, version VARCHAR(64) NOT NULL, installed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, node_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_agent_node ON agent (node_id)');
        $this->addSql('CREATE TABLE enrollment_token (id UUID NOT NULL, token_hash VARCHAR(64) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_enrollment_token_hash ON enrollment_token (token_hash)');
        $this->addSql('CREATE TABLE agent_credential (id UUID NOT NULL, secret_hash VARCHAR(64) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, agent_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_agent_credential_agent ON agent_credential (agent_id)');
        $this->addSql('ALTER TABLE agent ADD CONSTRAINT FK_268B9C9D460D9FD7 FOREIGN KEY (node_id) REFERENCES node (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE agent_credential ADD CONSTRAINT FK_7439CC443414710B FOREIGN KEY (agent_id) REFERENCES agent (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'This migration requires PostgreSQL.');

        $this->addSql('DROP TABLE agent_credential');
        $this->addSql('DROP TABLE agent');
        $this->addSql('DROP TABLE enrollment_token');
        $this->addSql('DROP TABLE node');
    }
}

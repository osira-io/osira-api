<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260826140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add AlertRule.impact_type to classify which Incidents contribute to SLA downtime.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'This migration requires PostgreSQL.');
        $this->addSql('ALTER TABLE alert_rules ADD impact_type VARCHAR(32) DEFAULT NULL');
        $this->addSql("UPDATE alert_rules SET impact_type = 'availability' WHERE impact_type IS NULL");
        $this->addSql('ALTER TABLE alert_rules ALTER impact_type SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'This migration requires PostgreSQL.');
        $this->addSql('ALTER TABLE alert_rules DROP impact_type');
    }
}

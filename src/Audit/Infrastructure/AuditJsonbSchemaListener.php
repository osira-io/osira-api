<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Tools\Event\GenerateSchemaTableEventArgs;

/** Keeps ORM-generated audit schemas aligned with the provider's native JSONB schema. */
#[AsDoctrineListener(event: 'postGenerateSchemaTable', priority: -100)]
final readonly class AuditJsonbSchemaListener
{
    public function postGenerateSchemaTable(GenerateSchemaTableEventArgs $event): void
    {
        if (!\in_array($event->getClassMetadata()->name, AuditEntityCatalog::ENTITIES, true)) {
            return;
        }

        $tableName = 'audit_'.$event->getClassMetadata()->getTableName();
        $schema = $event->getSchema();
        if (!$schema->hasTable($tableName)) {
            return;
        }

        $jsonb = Type::getType(Types::JSONB);
        $table = $schema->getTable($tableName);
        $table->getColumn('diffs')->setType($jsonb);
        $table->getColumn('extra_data')->setType($jsonb);
    }
}

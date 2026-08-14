<?php

declare(strict_types=1);

namespace App\Service\Audit;

use DH\Auditor\Model\Entry;
use Doctrine\DBAL\Connection;

/**
 * Osira-facing reader for the DH Auditor storage tables. Filtering, global ordering and
 * pagination are pushed down to PostgreSQL via a single `UNION ALL` query (plus a matching
 * `COUNT`), so this class never loads more rows than the requested page.
 *
 * @see AuditUnionQuery for the SQL construction.
 */
final readonly class AuditLogReader
{
    /** DH Auditor's own reader uses this timezone (see config/packages/dh_auditor.yaml). */
    private const string TIMEZONE = 'UTC';

    public function __construct(private Connection $connection)
    {
    }

    public function search(AuditSearchCriteria $criteria): AuditPage
    {
        $query = new AuditUnionQuery($criteria);

        $totalItemsValue = $this->connection->fetchOne($query->countSql(), $query->countParams());
        \assert(\is_int($totalItemsValue) || \is_string($totalItemsValue));
        $totalItems = (int) $totalItemsValue;
        $rows = $this->connection->fetchAllAssociative($query->selectSql(), $query->selectParams(), $query->selectTypes());

        return new AuditPage(array_map(self::toRecord(...), $rows), $totalItems);
    }

    /** @param array<string, mixed> $row */
    private static function toRecord(array $row): AuditRecord
    {
        $entity = $row['entity_type'];
        \assert(\is_string($entity));
        \assert(\is_string($row['created_at']));
        $row['created_at'] = new \DateTimeImmutable($row['created_at'], new \DateTimeZone(self::TIMEZONE));

        return new AuditRecord($entity, Entry::fromArray($row));
    }
}

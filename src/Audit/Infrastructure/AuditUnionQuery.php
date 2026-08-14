<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure;

use App\Audit\Domain\AuditSearchCriteria;
use Doctrine\DBAL\ParameterType;

/**
 * Builds the PostgreSQL `UNION ALL` query (and its matching `COUNT`) that reads every
 * DH Auditor storage table needed to answer an {@see AuditSearchCriteria} in a single
 * round-trip: filtering, global ordering, and pagination all happen in the database.
 *
 * Table identifiers are interpolated exclusively from {@see AuditEntityCatalog::TABLES},
 * an internal allowlist — never from a request-controlled value. Every filter VALUE is
 * bound as a DBAL parameter.
 */
final readonly class AuditUnionQuery
{
    /** @var list<string> */
    private const array COLUMNS = [
        'id', 'type', 'object_id', 'discriminator', 'transaction_hash',
        'diffs', 'extra_data', 'blame_id', 'blame_user', 'blame_user_fqdn',
        'blame_user_firewall', 'ip', 'created_at',
    ];

    private string $unionSql;

    /** @var array<string, mixed> */
    private array $unionParams;

    public function __construct(private AuditSearchCriteria $criteria)
    {
        $branches = [];
        $params = [];
        $index = 0;
        foreach (AuditEntityCatalog::tables($criteria->entity) as $entityName => $table) {
            [$branchSql, $branchParams] = self::buildBranch($table, $entityName, $criteria, $index);
            $branches[] = $branchSql;
            $params += $branchParams;
            ++$index;
        }

        $this->unionSql = implode(' UNION ALL ', $branches);
        $this->unionParams = $params;
    }

    public function selectSql(): string
    {
        return 'SELECT * FROM ('.$this->unionSql.') AS audits ORDER BY created_at DESC, id DESC, entity_type ASC LIMIT :audit_limit OFFSET :audit_offset';
    }

    public function countSql(): string
    {
        return 'SELECT COUNT(*) FROM ('.$this->unionSql.') AS audits';
    }

    /** @return array<string, mixed> */
    public function selectParams(): array
    {
        return [
            ...$this->unionParams,
            'audit_limit' => $this->criteria->itemsPerPage,
            'audit_offset' => ($this->criteria->page - 1) * $this->criteria->itemsPerPage,
        ];
    }

    /** @return array<string, mixed> */
    public function countParams(): array
    {
        return $this->unionParams;
    }

    /** @return array<string, ParameterType> */
    public function selectTypes(): array
    {
        return [
            'audit_limit' => ParameterType::INTEGER,
            'audit_offset' => ParameterType::INTEGER,
        ];
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private static function buildBranch(string $table, string $entityName, AuditSearchCriteria $criteria, int $index): array
    {
        $conditions = [];
        $params = ['entity_type_'.$index => $entityName];

        if (null !== $criteria->action) {
            $conditions[] = 'type = :action_'.$index;
            $params['action_'.$index] = $criteria->action;
        }
        if (null !== $criteria->entityId) {
            $conditions[] = 'object_id = :object_id_'.$index;
            $params['object_id_'.$index] = $criteria->entityId;
        }
        if (null !== $criteria->actorId) {
            $conditions[] = 'blame_id = :blame_id_'.$index;
            $params['blame_id_'.$index] = $criteria->actorId;
        }
        if (null !== $criteria->dateFrom) {
            $conditions[] = 'created_at >= :date_from_'.$index;
            $params['date_from_'.$index] = $criteria->dateFrom->format('Y-m-d H:i:s');
        }
        if (null !== $criteria->dateTo) {
            $conditions[] = 'created_at <= :date_to_'.$index;
            $params['date_to_'.$index] = $criteria->dateTo->format('Y-m-d H:i:s');
        }

        $where = [] === $conditions ? '' : ' WHERE '.implode(' AND ', $conditions);
        $columns = implode(', ', self::COLUMNS);
        $sql = "SELECT {$columns}, :entity_type_{$index} AS entity_type FROM {$table}{$where}";

        return [$sql, $params];
    }
}

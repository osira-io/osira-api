<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Audit;

use App\Service\Audit\AuditSearchCriteria;
use App\Service\Audit\AuditUnionQuery;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;

/**
 * Pure-PHP performance guard: proves the SQL builder pushes filtering, global ordering,
 * and pagination down to PostgreSQL instead of the old "fetch page x itemsPerPage rows
 * per table, merge and slice in PHP" strategy. No kernel, no database.
 */
final class AuditUnionQueryTest extends TestCase
{
    public function testUnboundedSearchUnionsEveryAuditedTable(): void
    {
        $query = new AuditUnionQuery(self::criteria());

        // 9 allow-listed tables joined pairwise -> 8 UNION ALL occurrences.
        self::assertSame(8, substr_count($query->selectSql(), 'UNION ALL'));
        foreach (['audit_users', 'audit_roles', 'audit_permissions', 'audit_nodes', 'audit_node_groups', 'audit_monitoring_templates', 'audit_item_definitions', 'audit_agents', 'audit_enrollment_tokens'] as $table) {
            self::assertStringContainsString('FROM '.$table, $query->selectSql());
        }
    }

    public function testEntityFilterUnionsOnlyTheSingleMatchingTable(): void
    {
        $query = new AuditUnionQuery(self::criteria(entity: 'Node'));

        self::assertSame(0, substr_count($query->selectSql(), 'UNION ALL'));
        self::assertStringContainsString('FROM audit_nodes', $query->selectSql());
        self::assertStringNotContainsString('audit_users', $query->selectSql());
    }

    public function testUnknownEntityIsRejectedBeforeAnySqlIsBuilt(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new AuditUnionQuery(self::criteria(entity: 'NotAnEntity'));
    }

    public function testLimitIsExactlyItemsPerPageNotPageTimesItemsPerPage(): void
    {
        $query = new AuditUnionQuery(self::criteria(page: 4, itemsPerPage: 20));

        // The old strategy fetched page x itemsPerPage (80) rows per table; the guard is LIMIT=itemsPerPage.
        self::assertSame(20, $query->selectParams()['audit_limit']);
        self::assertSame(60, $query->selectParams()['audit_offset']);
        self::assertSame(ParameterType::INTEGER, $query->selectTypes()['audit_limit']);
        self::assertSame(ParameterType::INTEGER, $query->selectTypes()['audit_offset']);
        self::assertStringContainsString('LIMIT :audit_limit OFFSET :audit_offset', $query->selectSql());
    }

    public function testCountQueryHasNoLimitOffsetOrOrderBy(): void
    {
        $query = new AuditUnionQuery(self::criteria());

        self::assertStringStartsWith('SELECT COUNT(*) FROM (', $query->countSql());
        self::assertStringNotContainsString('LIMIT', $query->countSql());
        self::assertStringNotContainsString('OFFSET', $query->countSql());
        self::assertStringNotContainsString('ORDER BY', $query->countSql());
        self::assertSame($query->countParams(), array_diff_key($query->selectParams(), ['audit_limit' => null, 'audit_offset' => null]));
    }

    public function testOrderIsGlobalAndDeterministic(): void
    {
        $query = new AuditUnionQuery(self::criteria());

        self::assertStringContainsString('ORDER BY created_at DESC, id DESC, entity_type ASC', $query->selectSql());
    }

    public function testFilterValuesAreBoundAsParametersNotConcatenated(): void
    {
        $query = new AuditUnionQuery(self::criteria(
            entity: 'Node',
            entityId: "'; DROP TABLE audit_nodes; --",
            action: 'update',
            actorId: 'actor-x',
        ));

        self::assertStringNotContainsString('DROP TABLE', $query->selectSql());
        self::assertStringContainsString('object_id = :object_id_0', $query->selectSql());
        self::assertStringContainsString('type = :action_0', $query->selectSql());
        self::assertStringContainsString('blame_id = :blame_id_0', $query->selectSql());
        self::assertSame("'; DROP TABLE audit_nodes; --", $query->selectParams()['object_id_0']);
    }

    private static function criteria(
        int $page = 1,
        int $itemsPerPage = 25,
        ?string $entity = null,
        ?string $entityId = null,
        ?string $action = null,
        ?string $actorId = null,
        ?\DateTimeImmutable $dateFrom = null,
        ?\DateTimeImmutable $dateTo = null,
    ): AuditSearchCriteria {
        return new AuditSearchCriteria($page, $itemsPerPage, $entity, $entityId, $action, $actorId, $dateFrom, $dateTo);
    }
}

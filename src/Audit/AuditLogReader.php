<?php

declare(strict_types=1);

namespace App\Audit;

use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\DateRangeFilter;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Query;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;

/** Osira-facing adapter around the DamienHarper Auditor reader. */
final readonly class AuditLogReader
{
    public function __construct(private Reader $reader)
    {
    }

    public function search(AuditSearchCriteria $criteria): AuditPage
    {
        $records = [];
        $totalItems = 0;
        $limitPerEntity = $criteria->page * $criteria->itemsPerPage;

        foreach (AuditEntityCatalog::entities($criteria->entity) as $name => $class) {
            $query = $this->reader->createQuery($class, [
                'type' => $criteria->action,
                'object_id' => $criteria->entityId,
                'blame_id' => $criteria->actorId,
                'page_size' => null,
            ]);
            if (null !== $criteria->dateFrom || null !== $criteria->dateTo) {
                $query->addFilter(new DateRangeFilter(Query::CREATED_AT, $criteria->dateFrom, $criteria->dateTo));
            }

            $totalItems += $query->count();
            foreach ($query->limit($limitPerEntity)->execute() as $entry) {
                $records[] = new AuditRecord($name, $entry);
            }
        }

        usort($records, self::compare(...));
        $offset = ($criteria->page - 1) * $criteria->itemsPerPage;

        return new AuditPage(\array_slice($records, $offset, $criteria->itemsPerPage), $totalItems);
    }

    private static function compare(AuditRecord $left, AuditRecord $right): int
    {
        $leftCreatedAt = $left->entry->createdAt?->getTimestamp() ?? 0;
        $rightCreatedAt = $right->entry->createdAt?->getTimestamp() ?? 0;
        $byDate = $rightCreatedAt <=> $leftCreatedAt;
        if (0 !== $byDate) {
            return $byDate;
        }

        $byId = ($right->entry->id ?? 0) <=> ($left->entry->id ?? 0);

        return 0 !== $byId ? $byId : $left->entity <=> $right->entity;
    }
}

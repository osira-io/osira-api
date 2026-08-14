<?php

declare(strict_types=1);

namespace App\Audit\Domain;

final readonly class AuditSearchCriteria
{
    public function __construct(
        public int $page,
        public int $itemsPerPage,
        public ?string $entity,
        public ?string $entityId,
        public ?string $action,
        public ?string $actorId,
        public ?\DateTimeImmutable $dateFrom,
        public ?\DateTimeImmutable $dateTo,
    ) {
    }
}

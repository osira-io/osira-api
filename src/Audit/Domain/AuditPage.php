<?php

declare(strict_types=1);

namespace App\Audit\Domain;

final readonly class AuditPage
{
    /** @param list<AuditRecord> $items */
    public function __construct(public array $items, public int $totalItems)
    {
    }
}

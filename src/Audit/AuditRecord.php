<?php

declare(strict_types=1);

namespace App\Audit;

use DH\Auditor\Model\Entry;

final readonly class AuditRecord
{
    public function __construct(public string $entity, public Entry $entry)
    {
    }
}

<?php

declare(strict_types=1);

namespace App\Dto\Sla;

final readonly class SlaReportPeriodOutput
{
    public function __construct(public \DateTimeImmutable $from, public \DateTimeImmutable $to)
    {
    }
}

<?php

declare(strict_types=1);

namespace App\Dto\Sla;

final readonly class SlaReportOutput
{
    /** @param list<SlaNodeReportOutput> $nodes */
    public function __construct(public SlaReportSummaryOutput $sla, public SlaReportPeriodOutput $period, public ?float $availabilityPercentage, public ?bool $compliant, public string $status, public int $totalPeriodSeconds, public int $excludedMaintenanceSeconds, public int $eligibleSeconds, public int $downtimeSeconds, public int $uptimeSeconds, public array $nodes)
    {
    }
}

<?php

declare(strict_types=1);

namespace App\Dto\Sla;

final readonly class SlaNodeReportOutput
{
    public function __construct(public string $id, public string $hostname, public ?string $displayName, public ?float $availabilityPercentage, public ?bool $compliant, public string $status, public int $totalPeriodSeconds, public int $excludedMaintenanceSeconds, public int $eligibleSeconds, public int $downtimeSeconds, public int $uptimeSeconds)
    {
    }
}

<?php

declare(strict_types=1);

namespace App\Service\Sla;

final readonly class SlaCalculationResult
{
    public function __construct(
        public float $targetPercentage,
        public ?float $availabilityPercentage,
        public ?bool $compliant,
        public SlaReportStatus $status,
        public int $totalPeriodSeconds,
        public int $excludedMaintenanceSeconds,
        public int $eligibleSeconds,
        public int $downtimeSeconds,
        public int $uptimeSeconds,
    ) {
    }
}

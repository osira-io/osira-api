<?php

declare(strict_types=1);

namespace App\Dto\Sla;

final readonly class SlaReportSummaryOutput
{
    public function __construct(public string $id, public string $name, public float $targetPercentage)
    {
    }
}

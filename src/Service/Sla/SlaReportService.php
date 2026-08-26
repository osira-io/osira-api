<?php

declare(strict_types=1);

namespace App\Service\Sla;

use App\Dto\Sla\SlaNodeReportOutput;
use App\Dto\Sla\SlaReportOutput;
use App\Dto\Sla\SlaReportPeriodOutput;
use App\Dto\Sla\SlaReportSummaryOutput;
use App\Entity\Alert\AlertRuleImpactType;
use App\Entity\Node\Node;
use App\Entity\Sla\Sla;
use App\Repository\Incident\IncidentRepository;
use App\Repository\Maintenance\MaintenanceWindowRepository;
use App\Repository\Node\NodeRepository;

final readonly class SlaReportService
{
    public function __construct(private NodeRepository $nodes, private IncidentRepository $incidents, private MaintenanceWindowRepository $maintenance, private SlaCalculator $calculator)
    {
    }

    public function create(Sla $sla, SlaPeriod $period): SlaReportOutput
    {
        $scope = [];
        foreach ($sla->nodes() as $node) {
            $scope[(string) $node->id()] = $node;
        }
        foreach ($this->nodes->findForGroups(array_values($sla->nodeGroups()->toArray())) as $node) {
            $scope[(string) $node->id()] = $node;
        }
        uasort($scope, static fn (Node $a, Node $b): int => [$a->hostname(), (string) $a->id()] <=> [$b->hostname(), (string) $b->id()]);
        $nodeOutputs = [];
        $total = $excluded = $eligible = $down = $up = 0;
        foreach ($scope as $node) {
            $incidentIntervals = [];
            foreach ($this->incidents->findIntersectingForNode($node, $period->from, $period->to) as $incident) {
                if (AlertRuleImpactType::AVAILABILITY !== $incident->alertRule()->impactType()) {
                    continue;
                }
                $incidentIntervals[] = new TimeInterval($incident->firstTriggeredAt(), $incident->resolvedAt() ?? $period->to);
            }
            $maintenanceIntervals = [];
            if ($sla->excludeMaintenance()) {
                foreach ($this->maintenance->findIntersectingForNode($node, $period->from, $period->to) as $window) {
                    $maintenanceIntervals[] = new TimeInterval($window->startsAt(), $window->endsAt());
                }
            }
            $result = $this->calculator->calculate($period->from, $period->to, $sla->targetPercentage(), $incidentIntervals, $maintenanceIntervals);
            $total += $result->totalPeriodSeconds;
            $excluded += $result->excludedMaintenanceSeconds;
            $eligible += $result->eligibleSeconds;
            $down += $result->downtimeSeconds;
            $up += $result->uptimeSeconds;
            $nodeOutputs[] = new SlaNodeReportOutput((string) $node->id(), $node->hostname(), $node->displayName(), $result->availabilityPercentage, $result->compliant, $result->status->value, $result->totalPeriodSeconds, $result->excludedMaintenanceSeconds, $result->eligibleSeconds, $result->downtimeSeconds, $result->uptimeSeconds);
        }

        if (0 === $eligible) {
            $availability = null;
            $compliant = null;
            $status = SlaReportStatus::NO_DATA;
        } else {
            $availability = round($up / $eligible * 100, 5);
            $compliant = $availability >= $sla->targetPercentage();
            $status = $compliant ? SlaReportStatus::COMPLIANT : SlaReportStatus::NON_COMPLIANT;
        }

        return new SlaReportOutput(new SlaReportSummaryOutput((string) $sla->id(), $sla->name(), $sla->targetPercentage()), new SlaReportPeriodOutput($period->from, $period->to), $availability, $compliant, $status->value, $total, $excluded, $eligible, $down, $up, $nodeOutputs);
    }
}

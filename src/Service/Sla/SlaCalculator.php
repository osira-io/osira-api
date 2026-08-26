<?php

declare(strict_types=1);

namespace App\Service\Sla;

final readonly class SlaCalculator
{
    /** @param list<TimeInterval> $incidentIntervals
     * @param list<TimeInterval> $excludedMaintenanceIntervals
     */
    public function calculate(\DateTimeImmutable $from, \DateTimeImmutable $to, float $targetPercentage, array $incidentIntervals, array $excludedMaintenanceIntervals): SlaCalculationResult
    {
        $period = new TimeInterval($from, $to);
        $incidents = $this->clipAndMerge($incidentIntervals, $period);
        $maintenance = $this->clipAndMerge($excludedMaintenanceIntervals, $period);
        $excludedSeconds = $this->duration($maintenance);
        $eligibleSeconds = $period->seconds() - $excludedSeconds;
        $incidentSeconds = $this->duration($incidents);
        $incidentDuringMaintenance = [];
        foreach ($incidents as $incident) {
            foreach ($maintenance as $window) {
                $intersection = $incident->intersect($window);
                if (null !== $intersection) {
                    $incidentDuringMaintenance[] = $intersection;
                }
            }
        }
        $downtimeSeconds = min($eligibleSeconds, max(0, $incidentSeconds - $this->duration($this->merge($incidentDuringMaintenance))));
        $uptimeSeconds = max(0, $eligibleSeconds - $downtimeSeconds);

        if (0 === $eligibleSeconds) {
            $availability = null;
            $compliant = null;
            $status = SlaReportStatus::NO_DATA;
        } else {
            $availability = round($uptimeSeconds / $eligibleSeconds * 100, 5);
            $compliant = $availability >= $targetPercentage;
            $status = $compliant ? SlaReportStatus::COMPLIANT : SlaReportStatus::NON_COMPLIANT;
        }

        return new SlaCalculationResult($targetPercentage, $availability, $compliant, $status, $period->seconds(), $excludedSeconds, $eligibleSeconds, $downtimeSeconds, $uptimeSeconds);
    }

    /** @param list<TimeInterval> $intervals
     * @return list<TimeInterval>
     */
    private function clipAndMerge(array $intervals, TimeInterval $period): array
    {
        $clipped = [];
        foreach ($intervals as $interval) {
            $intersection = $interval->intersect($period);
            if (null !== $intersection) {
                $clipped[] = $intersection;
            }
        }

        return $this->merge($clipped);
    }

    /** @param list<TimeInterval> $intervals
     * @return list<TimeInterval>
     */
    private function merge(array $intervals): array
    {
        usort($intervals, static fn (TimeInterval $left, TimeInterval $right): int => $left->from <=> $right->from);
        $merged = [];
        foreach ($intervals as $interval) {
            $lastIndex = \count($merged) - 1;
            if ($lastIndex < 0 || $interval->from > $merged[$lastIndex]->to) {
                $merged[] = $interval;
                continue;
            }
            if ($interval->to > $merged[$lastIndex]->to) {
                $merged[$lastIndex] = new TimeInterval($merged[$lastIndex]->from, $interval->to);
            }
        }

        return array_values($merged);
    }

    /** @param list<TimeInterval> $intervals */
    private function duration(array $intervals): int
    {
        return array_sum(array_map(static fn (TimeInterval $interval): int => $interval->seconds(), $intervals));
    }
}

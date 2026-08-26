<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Sla;

use App\Service\Sla\SlaCalculator;
use App\Service\Sla\SlaReportStatus;
use App\Service\Sla\TimeInterval;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SlaCalculatorTest extends TestCase
{
    public function testNoIncidentIsFullyAvailable(): void
    {
        $result = (new SlaCalculator())->calculate($this->at('00:00'), $this->at('01:00'), 99.9, [], []);

        self::assertSame(3600, $result->eligibleSeconds);
        self::assertSame(0, $result->downtimeSeconds);
        self::assertSame(100.0, $result->availabilityPercentage);
        self::assertTrue($result->compliant);
        self::assertSame(SlaReportStatus::COMPLIANT, $result->status);
    }

    /** @param list<array{string, string}> $incidents */
    #[DataProvider('incidentCases')]
    public function testIncidentIntervalsAreClippedMergedAndNeverDoubleCounted(array $incidents, int $expectedDowntime): void
    {
        $result = (new SlaCalculator())->calculate(
            $this->at('10:00'),
            $this->at('11:00'),
            99.0,
            array_map(fn (array $interval): TimeInterval => new TimeInterval($this->at($interval[0]), $this->at($interval[1])), $incidents),
            [],
        );

        self::assertSame($expectedDowntime, $result->downtimeSeconds);
    }

    /** @return iterable<string, array{list<array{string, string}>, int}> */
    public static function incidentCases(): iterable
    {
        yield 'simple' => [[['10:10', '10:30']], 1200];
        yield 'overlapping' => [[['10:00', '10:30'], ['10:10', '10:40']], 2400];
        yield 'disjoint' => [[['10:00', '10:10'], ['10:20', '10:30']], 1200];
        yield 'partially outside' => [[['09:30', '10:15'], ['10:50', '11:30']], 1500];
    }

    public function testExcludedOverlappingMaintenanceRemovesEligibilityAndIncidentDowntime(): void
    {
        $result = (new SlaCalculator())->calculate(
            $this->at('10:00'),
            $this->at('11:00'),
            90.0,
            [new TimeInterval($this->at('10:10'), $this->at('10:50'))],
            [new TimeInterval($this->at('10:00'), $this->at('10:20')), new TimeInterval($this->at('10:15'), $this->at('10:30'))],
        );

        self::assertSame(1800, $result->excludedMaintenanceSeconds);
        self::assertSame(1800, $result->eligibleSeconds);
        self::assertSame(1200, $result->downtimeSeconds);
        self::assertSame(600, $result->uptimeSeconds);
        self::assertFalse($result->compliant);
        self::assertSame(SlaReportStatus::NON_COMPLIANT, $result->status);
    }

    public function testMaintenanceCanRemainEligible(): void
    {
        $result = (new SlaCalculator())->calculate(
            $this->at('10:00'),
            $this->at('11:00'),
            50.0,
            [new TimeInterval($this->at('10:10'), $this->at('10:20'))],
            [],
        );

        self::assertSame(0, $result->excludedMaintenanceSeconds);
        self::assertSame(600, $result->downtimeSeconds);
        self::assertTrue($result->compliant);
        self::assertSame(SlaReportStatus::COMPLIANT, $result->status);
    }

    public function testFullyMaintenanceCoveredPeriodHasNoEligibleTimeAndIsNoData(): void
    {
        $result = (new SlaCalculator())->calculate(
            $this->at('10:00'),
            $this->at('11:00'),
            99.9,
            [new TimeInterval($this->at('10:15'), $this->at('10:45'))],
            [new TimeInterval($this->at('10:00'), $this->at('11:00'))],
        );

        self::assertSame(3600, $result->excludedMaintenanceSeconds);
        self::assertSame(0, $result->eligibleSeconds);
        self::assertSame(0, $result->downtimeSeconds);
        self::assertSame(0, $result->uptimeSeconds);
        self::assertNull($result->availabilityPercentage);
        self::assertNull($result->compliant);
        self::assertSame(SlaReportStatus::NO_DATA, $result->status);
    }

    private function at(string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-08-26 '.$time.':00+00:00');
    }
}

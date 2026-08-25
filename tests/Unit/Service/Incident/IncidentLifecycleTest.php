<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Incident;

use App\Entity\Alert\AlertOperator;
use App\Entity\Alert\AlertRule;
use App\Entity\Alert\AlertSeverity;
use App\Entity\Incident\IncidentStatus;
use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\ItemValueType;
use App\Entity\Node\Node;
use App\Factory\Incident\IncidentFactory;
use PHPUnit\Framework\TestCase;

final class IncidentLifecycleTest extends TestCase
{
    public function testOpeningUpdatingAndResolvingKeepsBusinessSnapshots(): void
    {
        $now = new \DateTimeImmutable('2026-08-25T12:00:00+00:00');
        [$node, $rule] = $this->subjects($now);
        $incident = (new IncidentFactory())->create($node, $rule, ['device' => '/dev/sda1'], '91.5', $now);

        self::assertSame(IncidentStatus::FIRING, $incident->status());
        self::assertSame(AlertSeverity::CRITICAL, $incident->severity());
        self::assertSame(1, $incident->occurrences());
        self::assertSame(['device' => '/dev/sda1'], $incident->labels());
        self::assertNotSame('', $incident->activeIdentity());

        $triggeredAt = $now->modify('+1 minute');
        $incident->recordTrigger('95', $triggeredAt);
        self::assertSame(2, $incident->occurrences());
        self::assertSame('95', $incident->lastValue());
        self::assertSame($triggeredAt, $incident->lastTriggeredAt());

        $resolvedAt = $now->modify('+2 minutes');
        $incident->resolve('79', $resolvedAt);
        self::assertSame(IncidentStatus::RESOLVED, $incident->status());
        self::assertSame($resolvedAt, $incident->resolvedAt());
        self::assertNull($incident->activeIdentity());
    }

    public function testDimensionsProduceDifferentLogicalIdentities(): void
    {
        $now = new \DateTimeImmutable('2026-08-25T12:00:00+00:00');
        [$node, $rule] = $this->subjects($now);
        $factory = new IncidentFactory();

        self::assertNotSame(
            $factory->create($node, $rule, ['device' => '/dev/sda1'], '91', $now)->activeIdentity(),
            $factory->create($node, $rule, ['device' => '/data'], '91', $now)->activeIdentity(),
        );
    }

    /** @return array{Node, AlertRule} */
    private function subjects(\DateTimeImmutable $now): array
    {
        $node = new Node('node-1', null, 'linux', 'amd64', $now, $now);
        $item = new ItemDefinition('custom.disk.usage', 'Disk', null, '%', ItemValueType::FLOAT, 60, 5, 'printf 1', null, true, $now);
        $rule = new AlertRule('Disk full', 'Disk full', 'Disk usage is high', $item, AlertOperator::GT, '90', '80', 300, 3, AlertSeverity::CRITICAL, true, $now);

        return [$node, $rule];
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Maintenance;

use App\Entity\Maintenance\MaintenanceWindow;
use App\Entity\Node\Node;
use App\Entity\NodeGroup\NodeGroup;
use App\Service\Maintenance\MaintenanceResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class MaintenanceResolverTest extends TestCase
{
    public function testResolvesDirectAndInheritedActiveMaintenanceWithDeterministicDedupedOrder(): void
    {
        $clock = new MockClock('2026-08-25T12:00:00+00:00');
        $resolver = new MaintenanceResolver($clock);
        $now = $clock->now();
        $node = new Node('prod-db-01', null, 'linux', 'amd64', $now, $now);
        $production = new NodeGroup('Production', null, $now);
        $postgres = new NodeGroup('PostgreSQL', null, $now);
        $node->replaceGroups([$postgres, $production]);

        $direct = $this->window('Direct maintenance', $now->modify('-1 hour'), $now->modify('+1 hour'), true, $now);
        $direct->replaceNodes([$node], $now);
        $prod = $this->window('Production upgrade', $now->modify('-1 hour'), $now->modify('+1 hour'), true, $now);
        $prod->replaceNodeGroups([$production], $now);
        $pg = $this->window('PostgreSQL patch', $now->modify('-1 hour'), $now->modify('+1 hour'), true, $now);
        $pg->replaceNodeGroups([$postgres], $now);
        $duplicate = $this->window('Direct maintenance', $now->modify('-1 hour'), $now->modify('+1 hour'), true, $now);
        $duplicate->replaceNodes([$node], $now);
        $duplicate->replaceNodeGroups([$production], $now);

        $active = $resolver->activeWindowsForNode($node, [$prod, $direct, $pg, $duplicate]);

        self::assertTrue($resolver->isInMaintenance($node, [$prod, $direct, $pg]));
        self::assertSame(
            ['Direct maintenance', 'Direct maintenance', 'PostgreSQL patch', 'Production upgrade'],
            array_map(static fn (MaintenanceWindow $window): string => $window->name(), $active),
        );
    }

    public function testIgnoresFuturePastDisabledAndUntargetedWindows(): void
    {
        $clock = new MockClock('2026-08-25T12:00:00+00:00');
        $resolver = new MaintenanceResolver($clock);
        $now = $clock->now();
        $node = new Node('prod-api-01', null, 'linux', 'amd64', $now, $now);
        $group = new NodeGroup('Production', null, $now);
        $node->replaceGroups([$group]);

        $future = $this->window('Future', $now->modify('+1 minute'), $now->modify('+1 hour'), true, $now);
        $future->replaceNodes([$node], $now);
        $past = $this->window('Past', $now->modify('-2 hours'), $now->modify('-1 second'), true, $now);
        $past->replaceNodeGroups([$group], $now);
        $disabled = $this->window('Disabled', $now->modify('-1 hour'), $now->modify('+1 hour'), false, $now);
        $disabled->replaceNodes([$node], $now);
        $other = $this->window('Other', $now->modify('-1 hour'), $now->modify('+1 hour'), true, $now);

        self::assertFalse($resolver->isInMaintenance($node, [$future, $past, $disabled, $other]));
        self::assertSame([], $resolver->activeWindowsForNode($node, [$future, $past, $disabled, $other]));
    }

    private function window(string $name, \DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt, bool $enabled, \DateTimeImmutable $now): MaintenanceWindow
    {
        return new MaintenanceWindow($name, null, $startsAt, $endsAt, $enabled, $now);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Monitoring;

use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\ItemValueType;
use App\Entity\Monitoring\MonitoringTemplate;
use App\Entity\Node\Node;
use App\Entity\NodeGroup\NodeGroup;
use App\Service\Monitoring\EffectiveNodeMonitoringResolver;
use PHPUnit\Framework\TestCase;

final class EffectiveNodeMonitoringResolverTest extends TestCase
{
    public function testResolverDeduplicatesTemplatesAndItemsAndIgnoresDisabledEntries(): void
    {
        $now = new \DateTimeImmutable('2026-08-19T10:00:00+00:00');

        $node = new Node('srv-prod-01', null, 'linux', 'x86_64', $now, $now);
        $group = new NodeGroup('Production Linux', null, $now);
        $node->replaceGroups([$group]);

        $cpu = new ItemDefinition('system.cpu.usage', 'CPU usage', null, 'System', '%', ItemValueType::FLOAT, 60, null, false, true, $now);
        $memory = new ItemDefinition('system.memory.usage', 'Memory usage', null, 'System', '%', ItemValueType::FLOAT, 60, null, false, true, $now);
        $disabledItem = new ItemDefinition('system.secret.disabled', 'Disabled item', null, 'System', null, ItemValueType::STRING, 60, null, false, false, $now);

        $linuxBase = new MonitoringTemplate('Linux Base', 'linux-base', null, false, true, $now);
        $linuxBase->replaceItemDefinitions([$cpu, $disabledItem], $now);

        $dockerBase = new MonitoringTemplate('Docker Base', 'docker-base', null, false, true, $now);
        $dockerBase->replaceItemDefinitions([$cpu, $memory], $now);

        $disabledTemplate = new MonitoringTemplate('Legacy Disabled', 'legacy-disabled', null, false, false, $now);
        $disabledTemplate->replaceItemDefinitions([$memory], $now);

        $node->replaceMonitoringTemplates([$linuxBase, $disabledTemplate]);
        $group->replaceMonitoringTemplates([$linuxBase, $dockerBase, $disabledTemplate], $now);

        $resolver = new EffectiveNodeMonitoringResolver();
        $resolved = $resolver->resolve($node);

        self::assertSame(['docker-base', 'linux-base'], array_map(
            static fn (MonitoringTemplate $template): string => $template->slug(),
            $resolved->templates,
        ));
        self::assertSame(['system.cpu.usage', 'system.memory.usage'], array_map(
            static fn (ItemDefinition $item): string => $item->key(),
            $resolved->items,
        ));
    }
}

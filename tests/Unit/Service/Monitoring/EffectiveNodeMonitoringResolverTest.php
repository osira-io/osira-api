<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Monitoring;

use App\Entity\Alert\AlertOperator;
use App\Entity\Alert\AlertRule;
use App\Entity\Alert\AlertRuleImpactType;
use App\Entity\Alert\AlertSeverity;
use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\ItemValueType;
use App\Entity\Monitoring\MonitoringTemplate;
use App\Entity\Node\Node;
use App\Entity\NodeGroup\NodeGroup;
use App\Service\Monitoring\EffectiveNodeMonitoringResolver;
use PHPUnit\Framework\TestCase;

final class EffectiveNodeMonitoringResolverTest extends TestCase
{
    public function testResolverUsesOnlyGroupTemplatesAndFiltersByOsEnabledStateAndMetricType(): void
    {
        $now = new \DateTimeImmutable('2026-08-19T10:00:00+00:00');

        $node = new Node('srv-prod-01', null, 'linux', 'x86_64', $now, $now);
        $group = new NodeGroup('Production Linux', null, $now);
        $node->replaceGroups([$group]);

        $cpu = new ItemDefinition('custom.cpu.usage', 'CPU usage', null, '%', ItemValueType::FLOAT, 60, 5, 'printf 90', null, true, $now);
        $memory = new ItemDefinition('custom.memory.usage', 'Memory usage', null, '%', ItemValueType::FLOAT, 60, 5, 'printf 80', 'Write-Output 80', true, $now);
        $windowsOnly = new ItemDefinition('custom.windows.only', 'Windows', null, null, ItemValueType::INTEGER, 60, 5, null, 'Write-Output 1', true, $now);
        $stringItem = new ItemDefinition('custom.string', 'String', null, null, ItemValueType::STRING, 60, 5, 'printf ok', null, true, $now);
        $disabledItem = new ItemDefinition('custom.disabled', 'Disabled item', null, null, ItemValueType::INTEGER, 60, 5, 'printf 1', null, false, $now);

        $linuxBase = new MonitoringTemplate('Linux', 'linux', null, true, $now);
        $linuxBase->replaceItemDefinitions([$cpu, $disabledItem], $now);

        $dockerBase = new MonitoringTemplate('Shared', 'shared', null, true, $now);
        $dockerBase->replaceItemDefinitions([$cpu, $memory, $windowsOnly, $stringItem], $now);

        $disabledTemplate = new MonitoringTemplate('Legacy Disabled', 'legacy-disabled', null, false, $now);
        $disabledTemplate->replaceItemDefinitions([$memory], $now);

        $group->replaceMonitoringTemplates([$linuxBase, $dockerBase, $disabledTemplate], $now);

        $templateRule = new AlertRule('CPU', 'CPU high', $cpu, AlertOperator::GT, '90', null, 300, 3, AlertSeverity::WARNING, AlertRuleImpactType::AVAILABILITY, true, $now);
        $groupRule = new AlertRule('Memory', 'Memory high', $memory, AlertOperator::GT, '90', null, 300, 3, AlertSeverity::WARNING, AlertRuleImpactType::AVAILABILITY, true, $now);
        $nodeRule = new AlertRule('Node CPU', 'CPU high', $cpu, AlertOperator::GT, '95', null, 300, 1, AlertSeverity::CRITICAL, AlertRuleImpactType::AVAILABILITY, true, $now);
        $disabledRule = new AlertRule('Disabled', 'Disabled', $cpu, AlertOperator::GT, '1', null, 60, 1, AlertSeverity::INFO, AlertRuleImpactType::AVAILABILITY, false, $now);
        $templateRule->assignToTemplate($linuxBase);
        $groupRule->assignToNodeGroup($group);
        $nodeRule->assignToNode($node);
        $disabledRule->assignToNode($node);

        $resolver = new EffectiveNodeMonitoringResolver();
        $resolved = $resolver->resolve($node);

        self::assertSame(['linux', 'shared'], array_map(
            static fn (MonitoringTemplate $template): string => $template->slug(),
            $resolved->templates,
        ));
        self::assertSame(['custom.cpu.usage', 'custom.memory.usage'], array_map(
            static fn (ItemDefinition $item): string => $item->key(),
            $resolved->items,
        ));

        $orphan = new Node('orphan', null, 'linux', 'x86_64', $now, $now);
        self::assertSame([], $resolver->resolve($orphan)->items);

        $windows = new Node('windows', null, 'windows', 'x86_64', $now, $now);
        $windows->replaceGroups([$group]);
        self::assertSame(['custom.memory.usage', 'custom.windows.only'], array_map(
            static fn (ItemDefinition $item): string => $item->key(),
            $resolver->resolve($windows)->items,
        ));
        self::assertSame(['CPU', 'Memory', 'Node CPU'], array_map(
            static fn (AlertRule $rule): string => $rule->name(),
            $resolver->getEffectiveAlertRules($node),
        ));
    }
}

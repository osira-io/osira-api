<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity\Alert;

use App\Entity\Alert\AlertOperator;
use App\Entity\Alert\AlertRule;
use App\Entity\Alert\AlertSeverity;
use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\ItemValueType;
use App\Entity\Monitoring\MonitoringTemplate;
use App\Entity\Node\Node;
use App\Entity\NodeGroup\NodeGroup;
use PHPUnit\Framework\TestCase;

final class AlertRuleAssignmentTest extends TestCase
{
    public function testAssignmentsRequireTheRuleItemAlongTheConfiguredPath(): void
    {
        $now = new \DateTimeImmutable();
        $item = new ItemDefinition('custom.cpu', 'CPU', null, null, ItemValueType::FLOAT, 60, 5, 'printf 1', null, true, $now);
        $other = new ItemDefinition('custom.other', 'Other', null, null, ItemValueType::FLOAT, 60, 5, 'printf 1', null, true, $now);
        $template = new MonitoringTemplate('Linux', 'linux', null, true, $now);
        $template->replaceItemDefinitions([$other], $now);
        $rule = new AlertRule('CPU', 'CPU', 'CPU', $item, AlertOperator::GT, '90', null, 60, 1, AlertSeverity::WARNING, true, $now);

        $this->expectException(\InvalidArgumentException::class);
        $rule->assignToTemplate($template);
    }

    public function testNodeAssignmentRejectsAnOsIncompatibleItem(): void
    {
        $now = new \DateTimeImmutable();
        $item = new ItemDefinition('custom.windows', 'Windows', null, null, ItemValueType::INTEGER, 60, 5, null, 'Write-Output 1', true, $now);
        $template = new MonitoringTemplate('Windows', 'windows', null, true, $now);
        $template->replaceItemDefinitions([$item], $now);
        $group = new NodeGroup('Mixed', null, $now);
        $group->replaceMonitoringTemplates([$template], $now);
        $node = new Node('linux-node', null, 'linux', 'x86_64', $now, $now);
        $node->replaceGroups([$group]);
        $rule = new AlertRule('Windows', 'Windows', 'Windows', $item, AlertOperator::GT, '1', null, 60, 1, AlertSeverity::WARNING, true, $now);

        $this->expectException(\InvalidArgumentException::class);
        $rule->assignToNode($node);
    }
}

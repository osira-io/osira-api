<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Monitoring;

use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\ItemValueType;
use App\Entity\Monitoring\MonitoringTemplate;
use App\Entity\Node\Node;
use App\Entity\NodeGroup\NodeGroup;
use App\Service\Monitoring\ItemDefinitionOutputFactory;
use App\Service\Monitoring\MonitoringTemplateOutputFactory;
use PHPUnit\Framework\TestCase;

final class MonitoringTemplateOutputFactoryTest extends TestCase
{
    public function testCreateBuildsDetailedOutput(): void
    {
        $now = new \DateTimeImmutable('2026-08-19T12:00:00+00:00');
        $template = new MonitoringTemplate('Linux Base', 'linux-base', 'Core Linux metrics', false, true, $now);
        $item = new ItemDefinition('system.cpu.usage', 'CPU usage', 'desc', 'System', '%', ItemValueType::FLOAT, 60, 5, false, true, $now);
        $node = new Node('srv-01', 'Node 01', 'linux', 'x86_64', $now, $now);
        $group = new NodeGroup('Linux', null, $now);

        $template->replaceItemDefinitions([$item], $now);
        $node->replaceMonitoringTemplates([$template]);
        $group->replaceMonitoringTemplates([$template], $now);

        $factory = new MonitoringTemplateOutputFactory(new ItemDefinitionOutputFactory());
        $output = $factory->create($template);
        $summary = $factory->createSummary($template);

        self::assertSame((string) $template->id(), $output->id);
        self::assertSame('Linux Base', $output->name);
        self::assertSame('linux-base', $output->slug);
        self::assertSame('Core Linux metrics', $output->description);
        self::assertTrue($output->isEnabled);
        self::assertCount(1, $output->itemDefinitions);
        self::assertSame('system.cpu.usage', $output->itemDefinitions[0]->key);
        self::assertCount(1, $output->nodes);
        self::assertSame('srv-01', $output->nodes[0]->hostname);
        self::assertCount(1, $output->nodeGroups);
        self::assertSame('Linux', $output->nodeGroups[0]->name);
        self::assertSame('Linux Base', $summary->name);
        self::assertSame('linux-base', $summary->slug);
    }
}

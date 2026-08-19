<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity\Monitoring;

use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\ItemValueType;
use App\Entity\Monitoring\MonitoringTemplate;
use App\Entity\Node\Node;
use App\Entity\NodeGroup\NodeGroup;
use PHPUnit\Framework\TestCase;

final class MonitoringEntitiesTest extends TestCase
{
    public function testItemDefinitionUpdateAndSynchronizeRefreshFields(): void
    {
        $createdAt = new \DateTimeImmutable('2026-08-19T08:00:00+00:00');
        $updatedAt = new \DateTimeImmutable('2026-08-19T09:00:00+00:00');
        $synchronizedAt = new \DateTimeImmutable('2026-08-19T10:00:00+00:00');

        $item = new ItemDefinition('system.cpu.usage', 'CPU', null, null, null, ItemValueType::FLOAT, 60, null, false, true, $createdAt);
        $item->update('system.cpu.total', 'CPU total', 'desc', 'System', '%', ItemValueType::INTEGER, 30, 5, false, $updatedAt);

        self::assertSame('system.cpu.total', $item->key());
        self::assertSame('CPU total', $item->name());
        self::assertSame('desc', $item->description());
        self::assertSame('System', $item->category());
        self::assertSame('%', $item->unit());
        self::assertSame(ItemValueType::INTEGER, $item->valueType());
        self::assertSame(30, $item->intervalSeconds());
        self::assertSame(5, $item->timeoutSeconds());
        self::assertFalse($item->isEnabled());
        self::assertSame($updatedAt, $item->updatedAt());

        $item->synchronize('CPU synced', null, 'OS', 'ms', ItemValueType::STRING, 15, null, true, $synchronizedAt);

        self::assertSame('CPU synced', $item->name());
        self::assertNull($item->description());
        self::assertSame('OS', $item->category());
        self::assertSame('ms', $item->unit());
        self::assertSame(ItemValueType::STRING, $item->valueType());
        self::assertSame(15, $item->intervalSeconds());
        self::assertNull($item->timeoutSeconds());
        self::assertTrue($item->isEnabled());
        self::assertSame($synchronizedAt, $item->updatedAt());
    }

    public function testMonitoringTemplateSynchronizesRelationshipsAndTimestamps(): void
    {
        $createdAt = new \DateTimeImmutable('2026-08-19T08:00:00+00:00');
        $updatedAt = new \DateTimeImmutable('2026-08-19T09:00:00+00:00');
        $replacedAt = new \DateTimeImmutable('2026-08-19T10:00:00+00:00');
        $syncedAt = new \DateTimeImmutable('2026-08-19T11:00:00+00:00');

        $cpu = new ItemDefinition('system.cpu.usage', 'CPU', null, null, null, ItemValueType::FLOAT, 60, null, false, true, $createdAt);
        $memory = new ItemDefinition('system.memory.usage', 'Memory', null, null, null, ItemValueType::FLOAT, 60, null, false, true, $createdAt);
        $template = new MonitoringTemplate('Linux Base', 'linux-base', null, false, true, $createdAt);

        $template->update('Linux Core', 'linux-core', 'desc', false, $updatedAt);
        $template->replaceItemDefinitions([$cpu, $cpu, $memory], $replacedAt);
        $template->synchronize('Linux Synced', 'catalog', true, $syncedAt);

        self::assertSame('Linux Synced', $template->name());
        self::assertSame('linux-core', $template->slug());
        self::assertSame('catalog', $template->description());
        self::assertTrue($template->isEnabled());
        self::assertCount(2, $template->itemDefinitions());
        self::assertSame($syncedAt, $template->updatedAt());

        $node = new Node('srv-01', 'Node 01', 'linux', 'x86_64', $createdAt, $createdAt);
        $group = new NodeGroup('Linux group', null, $createdAt);

        $node->replaceMonitoringTemplates([$template]);
        $group->replaceMonitoringTemplates([$template], $createdAt);
        self::assertCount(1, $template->nodes());
        self::assertCount(1, $template->nodeGroups());

        $node->replaceMonitoringTemplates([]);
        $group->replaceMonitoringTemplates([], $updatedAt);
        self::assertCount(0, $template->nodes());
        self::assertCount(0, $template->nodeGroups());
    }
}

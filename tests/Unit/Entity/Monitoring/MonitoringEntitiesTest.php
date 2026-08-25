<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity\Monitoring;

use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\ItemValueType;
use App\Entity\Monitoring\MonitoringTemplate;
use App\Entity\NodeGroup\NodeGroup;
use PHPUnit\Framework\TestCase;

final class MonitoringEntitiesTest extends TestCase
{
    public function testItemDefinitionUpdateRefreshesCustomCollectorFields(): void
    {
        $createdAt = new \DateTimeImmutable('2026-08-19T08:00:00+00:00');
        $updatedAt = new \DateTimeImmutable('2026-08-19T09:00:00+00:00');
        $item = new ItemDefinition('custom.cpu.usage', 'CPU', null, '%', ItemValueType::FLOAT, 60, 5, 'printf 1', null, true, $createdAt);
        $item->update('custom.cpu.total', 'CPU total', 'desc', '%', ItemValueType::INTEGER, 30, 5, 'printf 2', 'Write-Output 2', false, $updatedAt);

        self::assertSame('custom.cpu.total', $item->key());
        self::assertSame('CPU total', $item->name());
        self::assertSame('desc', $item->description());
        self::assertSame('%', $item->unit());
        self::assertSame(ItemValueType::INTEGER, $item->valueType());
        self::assertSame(30, $item->intervalSeconds());
        self::assertSame(5, $item->timeoutSeconds());
        self::assertSame('printf 2', $item->linuxCommand());
        self::assertSame('Write-Output 2', $item->windowsCommand());
        self::assertFalse($item->isEnabled());
        self::assertSame($updatedAt, $item->updatedAt());
    }

    public function testMonitoringTemplateSynchronizesRelationshipsAndTimestamps(): void
    {
        $createdAt = new \DateTimeImmutable('2026-08-19T08:00:00+00:00');
        $updatedAt = new \DateTimeImmutable('2026-08-19T09:00:00+00:00');
        $replacedAt = new \DateTimeImmutable('2026-08-19T10:00:00+00:00');
        $cpu = new ItemDefinition('custom.cpu.usage', 'CPU', null, null, ItemValueType::FLOAT, 60, 5, 'printf 1', null, true, $createdAt);
        $memory = new ItemDefinition('custom.memory.usage', 'Memory', null, null, ItemValueType::FLOAT, 60, 5, 'printf 1', null, true, $createdAt);
        $template = new MonitoringTemplate('Linux', 'linux', null, true, $createdAt);

        $template->update('Linux Core', 'linux-core', 'desc', false, $updatedAt);
        $template->replaceItemDefinitions([$cpu, $cpu, $memory], $replacedAt);
        self::assertSame('Linux Core', $template->name());
        self::assertSame('linux-core', $template->slug());
        self::assertSame('desc', $template->description());
        self::assertFalse($template->isEnabled());
        self::assertCount(2, $template->itemDefinitions());
        self::assertSame($replacedAt, $template->updatedAt());

        $group = new NodeGroup('Linux group', null, $createdAt);

        $group->replaceMonitoringTemplates([$template], $createdAt);
        self::assertCount(1, $template->nodeGroups());

        $group->replaceMonitoringTemplates([], $updatedAt);
        self::assertCount(0, $template->nodeGroups());
    }
}

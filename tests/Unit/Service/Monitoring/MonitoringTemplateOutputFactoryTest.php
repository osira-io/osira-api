<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Monitoring;

use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\ItemValueType;
use App\Entity\Monitoring\MonitoringTemplate;
use App\Entity\NodeGroup\NodeGroup;
use App\Service\Monitoring\ItemDefinitionOutputFactory;
use App\Service\Monitoring\MonitoringTemplateOutputFactory;
use PHPUnit\Framework\TestCase;

final class MonitoringTemplateOutputFactoryTest extends TestCase
{
    public function testCreateBuildsDetailedOutput(): void
    {
        $now = new \DateTimeImmutable('2026-08-19T12:00:00+00:00');
        $template = new MonitoringTemplate('Linux', 'linux', 'Core Linux metrics', true, $now);
        $item = new ItemDefinition('custom.cpu.usage', 'CPU usage', 'desc', '%', ItemValueType::FLOAT, 60, 5, 'printf 1', null, true, $now);
        $group = new NodeGroup('Linux', null, $now);

        $template->replaceItemDefinitions([$item], $now);
        $group->replaceMonitoringTemplates([$template], $now);

        $factory = new MonitoringTemplateOutputFactory(new ItemDefinitionOutputFactory());
        $output = $factory->create($template);
        $summary = $factory->createSummary($template);

        self::assertSame((string) $template->id(), $output->id);
        self::assertSame('Linux', $output->name);
        self::assertSame('linux', $output->slug);
        self::assertSame('Core Linux metrics', $output->description);
        self::assertTrue($output->isEnabled);
        self::assertCount(1, $output->itemDefinitions);
        self::assertSame('custom.cpu.usage', $output->itemDefinitions[0]->key);
        self::assertCount(1, $output->nodeGroups);
        self::assertSame('Linux', $output->nodeGroups[0]->name);
        self::assertSame('Linux', $summary->name);
        self::assertSame('linux', $summary->slug);
    }
}

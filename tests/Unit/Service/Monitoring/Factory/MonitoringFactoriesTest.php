<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Monitoring\Factory;

use App\Entity\Monitoring\ItemValueType;
use App\Service\Monitoring\Factory\ItemDefinitionFactory;
use App\Service\Monitoring\Factory\MonitoringTemplateFactory;
use App\Service\Shared\Exception\ResourceValidationException;
use PHPUnit\Framework\TestCase;

final class MonitoringFactoriesTest extends TestCase
{
    public function testItemDefinitionFactoryNormalizesCreationValues(): void
    {
        $factory = new ItemDefinitionFactory();
        $now = new \DateTimeImmutable('2026-08-19T12:00:00+00:00');

        $item = $factory->create(
            '  CUSTOM.CHECK.LATENCY  ',
            '  Custom latency  ',
            '  Probe latency  ',
            '  Custom  ',
            '  ms  ',
            ItemValueType::FLOAT,
            30,
            5,
            false,
            true,
            $now,
        );

        self::assertSame('custom.check.latency', $item->key());
        self::assertSame('Custom latency', $item->name());
        self::assertSame('Probe latency', $item->description());
        self::assertSame('Custom', $item->category());
        self::assertSame('ms', $item->unit());
        self::assertSame(ItemValueType::FLOAT, $item->valueType());
        self::assertSame(30, $item->intervalSeconds());
        self::assertSame(5, $item->timeoutSeconds());
    }

    public function testMonitoringTemplateFactoryNormalizesCreationValues(): void
    {
        $factory = new MonitoringTemplateFactory();
        $now = new \DateTimeImmutable('2026-08-19T12:00:00+00:00');

        $template = $factory->create('  Linux Base  ', null, '  Core metrics  ', false, true, $now);

        self::assertSame('Linux Base', $template->name());
        self::assertSame('linux-base', $template->slug());
        self::assertSame('Core metrics', $template->description());
        self::assertTrue($template->isEnabled());
    }

    public function testItemDefinitionFactoryRejectsInvalidKey(): void
    {
        $factory = new ItemDefinitionFactory();

        $this->expectException(ResourceValidationException::class);
        $factory->normalizeKey('invalid key');
    }
}

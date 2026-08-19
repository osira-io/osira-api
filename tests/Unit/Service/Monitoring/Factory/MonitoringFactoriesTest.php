<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Monitoring\Factory;

use App\Entity\Monitoring\ItemValueType;
use App\Factory\Monitoring\ItemDefinitionFactory;
use App\Factory\Monitoring\MonitoringTemplateFactory;
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

    public function testItemDefinitionFactoryNormalizesNullableValuesAndDefaults(): void
    {
        $factory = new ItemDefinitionFactory();

        self::assertNull($factory->normalizeNullable(null));
        self::assertNull($factory->normalizeNullable('   '));
        self::assertSame('Category', $factory->normalizeNullable('  Category  '));
        self::assertNull($factory->normalizeNullablePositive(null, 'unused'));
    }

    public function testItemDefinitionFactoryRejectsBlankName(): void
    {
        $factory = new ItemDefinitionFactory();

        $this->expectException(ResourceValidationException::class);
        $factory->normalizeName('   ');
    }

    public function testItemDefinitionFactoryRejectsNonPositiveInterval(): void
    {
        $factory = new ItemDefinitionFactory();

        $this->expectException(ResourceValidationException::class);
        $factory->normalizePositive(0, 'interval');
    }

    public function testItemDefinitionFactoryRejectsNonPositiveTimeout(): void
    {
        $factory = new ItemDefinitionFactory();

        $this->expectException(ResourceValidationException::class);
        $factory->normalizeNullablePositive(0, 'timeout');
    }

    public function testMonitoringTemplateFactoryRejectsBlankName(): void
    {
        $factory = new MonitoringTemplateFactory();

        $this->expectException(ResourceValidationException::class);
        $factory->normalizeName('   ');
    }

    public function testMonitoringTemplateFactoryRejectsInvalidSlug(): void
    {
        $factory = new MonitoringTemplateFactory();

        $this->expectException(ResourceValidationException::class);
        $factory->normalizeSlug('---');
    }

    public function testMonitoringTemplateFactoryNormalizesBlankDescriptionToNull(): void
    {
        $factory = new MonitoringTemplateFactory();

        self::assertNull($factory->normalizeDescription('   '));
        self::assertNull($factory->normalizeDescription(null));
    }
}

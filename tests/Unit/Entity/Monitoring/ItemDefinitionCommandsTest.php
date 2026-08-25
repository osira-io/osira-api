<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity\Monitoring;

use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\ItemValueType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ItemDefinition::class)]
final class ItemDefinitionCommandsTest extends TestCase
{
    public function testCommandsAreSelectedFailSafeForTheNodeOperatingSystem(): void
    {
        $item = new ItemDefinition(
            'custom.service.status',
            'Service status',
            null,
            null,
            ItemValueType::BOOLEAN,
            30,
            5,
            'systemctl is-active nginx',
            'Get-Service nginx | Select-Object -ExpandProperty Status',
            true,
            new \DateTimeImmutable(),
        );

        self::assertSame('systemctl is-active nginx', $item->commandForOs('linux'));
        self::assertSame('Get-Service nginx | Select-Object -ExpandProperty Status', $item->commandForOs('windows'));
        self::assertNull($item->commandForOs('freebsd'));
    }
}

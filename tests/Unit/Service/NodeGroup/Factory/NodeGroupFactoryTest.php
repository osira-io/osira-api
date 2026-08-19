<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\NodeGroup\Factory;

use App\Factory\NodeGroup\NodeGroupFactory;
use App\Service\Shared\Exception\ResourceValidationException;
use PHPUnit\Framework\TestCase;

final class NodeGroupFactoryTest extends TestCase
{
    public function testCreateNormalizesNameAndDescription(): void
    {
        $factory = new NodeGroupFactory();
        $now = new \DateTimeImmutable('2026-08-19T12:00:00+00:00');

        $group = $factory->create('  Production  ', '  Live traffic  ', $now);

        self::assertSame('Production', $group->name());
        self::assertSame('Live traffic', $group->description());
        self::assertSame($now, $group->createdAt());
        self::assertSame($now, $group->updatedAt());
    }

    public function testNormalizeNameRejectsBlankValue(): void
    {
        $factory = new NodeGroupFactory();

        $this->expectException(ResourceValidationException::class);
        $factory->normalizeName('   ');
    }

    public function testNormalizeDescriptionConvertsBlankToNull(): void
    {
        $factory = new NodeGroupFactory();

        self::assertNull($factory->normalizeDescription('   '));
        self::assertNull($factory->normalizeDescription(null));
    }
}

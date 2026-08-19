<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Node\Factory;

use App\Service\Node\Factory\NodeFactory;
use App\Service\Shared\Exception\ResourceValidationException;
use PHPUnit\Framework\TestCase;

final class NodeFactoryTest extends TestCase
{
    public function testCreateNormalizesInitialNodeValues(): void
    {
        $factory = new NodeFactory();
        $now = new \DateTimeImmutable('2026-08-19T12:00:00+00:00');

        $node = $factory->create('  srv-prod-01  ', '  Production  ', ' linux ', ' x86_64 ', $now, $now);

        self::assertSame('srv-prod-01', $node->hostname());
        self::assertSame('Production', $node->displayName());
        self::assertSame('linux', $node->os());
        self::assertSame('x86_64', $node->architecture());
        self::assertSame($now, $node->firstSeenAt());
        self::assertSame($now, $node->createdAt());
    }

    public function testCreateRejectsBlankHostname(): void
    {
        $factory = new NodeFactory();

        $this->expectException(ResourceValidationException::class);
        $factory->create('  ', null, 'linux', 'x86_64', new \DateTimeImmutable());
    }

    public function testCreateNormalizesBlankDisplayNameToNullAndDefaultsCreatedAt(): void
    {
        $factory = new NodeFactory();
        $firstSeenAt = new \DateTimeImmutable('2026-08-19T12:00:00+00:00');

        $node = $factory->create('srv-prod-01', '   ', 'linux', 'x86_64', $firstSeenAt);

        self::assertNull($node->displayName());
        self::assertSame($firstSeenAt, $node->createdAt());
    }

    public function testCreateRejectsBlankOs(): void
    {
        $factory = new NodeFactory();
        $firstSeenAt = new \DateTimeImmutable('2026-08-19T12:00:00+00:00');

        $this->expectException(ResourceValidationException::class);
        $factory->create('srv-prod-01', null, '   ', 'x86_64', $firstSeenAt);
    }

    public function testCreateRejectsBlankArchitecture(): void
    {
        $factory = new NodeFactory();
        $firstSeenAt = new \DateTimeImmutable('2026-08-19T12:00:00+00:00');

        $this->expectException(ResourceValidationException::class);
        $factory->create('srv-prod-01', null, 'linux', '   ', $firstSeenAt);
    }
}

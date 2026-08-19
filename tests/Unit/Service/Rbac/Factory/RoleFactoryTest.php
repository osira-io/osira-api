<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Rbac\Factory;

use App\Service\Rbac\Factory\RoleFactory;
use App\Service\Shared\Exception\ResourceValidationException;
use PHPUnit\Framework\TestCase;

final class RoleFactoryTest extends TestCase
{
    public function testCreateNormalizesNameSlugAndDescription(): void
    {
        $factory = new RoleFactory();
        $now = new \DateTimeImmutable('2026-08-19T12:00:00+00:00');

        $role = $factory->create('  NOC Operator  ', null, '  Handles ops  ', false, $now);

        self::assertSame('NOC Operator', $role->name());
        self::assertSame('noc-operator', $role->slug());
        self::assertSame('Handles ops', $role->description());
        self::assertFalse($role->isSystem());
        self::assertSame($now, $role->createdAt());
        self::assertSame($now, $role->updatedAt());
    }

    public function testNormalizeSlugRejectsInvalidResult(): void
    {
        $factory = new RoleFactory();

        $this->expectException(ResourceValidationException::class);
        $factory->normalizeSlug('---');
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\User\Factory;

use App\Service\Shared\Exception\ResourceValidationException;
use App\Service\User\Factory\UserFactory;
use PHPUnit\Framework\TestCase;

final class UserFactoryTest extends TestCase
{
    public function testCreateNormalizesEmailAndInitializesRoleDefaults(): void
    {
        $factory = new UserFactory();
        $now = new \DateTimeImmutable('2026-08-19T12:00:00+00:00');

        $user = $factory->create('  ADMIN@Example.com  ', $now);

        self::assertSame('admin@example.com', $user->getUserIdentifier());
        self::assertSame(['ROLE_USER'], $user->getRoles());
        self::assertSame($now, $user->createdAt());
        self::assertSame($now, $user->updatedAt());
    }

    public function testNormalizeEmailRejectsBlankValue(): void
    {
        $factory = new UserFactory();

        $this->expectException(ResourceValidationException::class);
        $factory->normalizeEmail('   ');
    }

    public function testCreateKeepsCustomTechnicalRoles(): void
    {
        $factory = new UserFactory();
        $now = new \DateTimeImmutable('2026-08-19T12:00:00+00:00');

        $user = $factory->create('operator@example.com', $now, ['ROLE_OPERATOR']);

        self::assertSame(['ROLE_OPERATOR', 'ROLE_USER'], $user->getRoles());
    }
}

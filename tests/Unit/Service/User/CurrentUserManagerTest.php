<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\User;

use App\Entity\User\User;
use App\Service\Shared\Exception\ResourceValidationException;
use App\Service\User\CurrentUserManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class CurrentUserManagerTest extends TestCase
{
    public function testUpdateLocalePersistsNormalizedLocale(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $clock = self::createStub(ClockInterface::class);
        $now = new \DateTimeImmutable('2026-08-19T12:00:00+00:00');
        $clock->method('now')->willReturn($now);
        $entityManager->expects(self::once())->method('flush');

        $manager = new CurrentUserManager($entityManager, $clock);
        $user = new User('admin@example.com', ['ROLE_USER'], new \DateTimeImmutable('2026-08-18T12:00:00+00:00'));

        $updatedUser = $manager->updateLocale($user, 'EN');

        self::assertSame($user, $updatedUser);
        self::assertSame('en', $user->locale());
        self::assertSame($now, $user->updatedAt());
    }

    public function testUpdateLocaleRejectsUnsupportedLocale(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $clock = self::createStub(ClockInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $manager = new CurrentUserManager($entityManager, $clock);
        $user = new User('admin@example.com', ['ROLE_USER'], new \DateTimeImmutable('2026-08-18T12:00:00+00:00'));

        $this->expectException(ResourceValidationException::class);
        $manager->updateLocale($user, 'de');
    }
}

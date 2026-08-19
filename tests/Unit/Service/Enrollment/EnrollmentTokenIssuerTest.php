<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Enrollment;

use App\Entity\Enrollment\EnrollmentToken;
use App\Security\Auth\TokenGenerator;
use App\Security\Auth\TokenHasher;
use App\Service\Enrollment\EnrollmentTokenIssuer;
use App\Service\Enrollment\Factory\EnrollmentTokenFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class EnrollmentTokenIssuerTest extends TestCase
{
    public function testIssueCreatesAndPersistsHashedEnrollmentToken(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $tokenGenerator = new TokenGenerator();
        $tokenHasher = new TokenHasher('test-pepper');
        $now = new \DateTimeImmutable('2026-08-19T12:00:00+00:00');
        $expiresAt = new \DateTimeImmutable('2026-08-19T13:00:00+00:00');
        $clock = new readonly class($now) implements ClockInterface {
            public function __construct(private \DateTimeImmutable $now)
            {
            }

            public function now(): \DateTimeImmutable
            {
                return $this->now;
            }
        };
        $factory = new EnrollmentTokenFactory();
        $persistedToken = null;

        $entityManager->expects(self::once())->method('persist')->willReturnCallback(static function (EnrollmentToken $token) use (&$persistedToken): void {
            $persistedToken = $token;
        });
        $entityManager->expects(self::once())->method('flush');

        $issuer = new EnrollmentTokenIssuer($entityManager, $tokenGenerator, $tokenHasher, $clock, 3600, $factory);

        $issuedToken = $issuer->issue();

        self::assertInstanceOf(EnrollmentToken::class, $persistedToken);
        self::assertStringStartsWith(TokenGenerator::ENROLLMENT_PREFIX, $issuedToken->rawToken);
        self::assertSame($now, $issuedToken->enrollmentToken->createdAt());
        self::assertEquals($expiresAt, $issuedToken->enrollmentToken->expiresAt());
        self::assertSame($issuedToken->enrollmentToken->tokenHash(), $persistedToken->tokenHash());
        self::assertTrue($tokenHasher->verify($issuedToken->rawToken, $issuedToken->enrollmentToken->tokenHash()));
    }
}

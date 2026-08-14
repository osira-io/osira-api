<?php

declare(strict_types=1);

namespace App\Entity\Enrollment;

use App\Repository\Enrollment\EnrollmentTokenRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: EnrollmentTokenRepository::class)]
#[ORM\Table(name: 'enrollment_tokens')]
#[ORM\UniqueConstraint(name: 'uniq_enrollment_tokens_hash', columns: ['token_hash'])]
final class EnrollmentToken
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private readonly Ulid $id;

    #[ORM\Column(length: 64)]
    private readonly string $tokenHash;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private readonly \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private readonly \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    public function __construct(string $tokenHash, \DateTimeImmutable $createdAt, \DateTimeImmutable $expiresAt)
    {
        $this->id = new Ulid();
        $this->tokenHash = $tokenHash;
        $this->createdAt = $createdAt;
        $this->expiresAt = $expiresAt;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function usedAt(): ?\DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function isExpiredAt(\DateTimeImmutable $at): bool
    {
        return $this->expiresAt <= $at;
    }

    public function markUsed(\DateTimeImmutable $usedAt): void
    {
        if (null !== $this->usedAt) {
            throw new \LogicException('An enrollment token cannot be used twice.');
        }

        $this->usedAt = $usedAt;
    }
}

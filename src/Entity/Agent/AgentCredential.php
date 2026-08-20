<?php

declare(strict_types=1);

namespace App\Entity\Agent;

use App\Repository\Agent\AgentCredentialRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: AgentCredentialRepository::class)]
#[ORM\Table(name: 'agent_credentials')]
#[ORM\Index(columns: ['agent_id'], name: 'idx_agent_credentials_agent')]
#[ORM\UniqueConstraint(name: 'uniq_agent_credentials_secret_hash', columns: ['secret_hash'])]
final class AgentCredential
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private readonly Ulid $id;

    #[ORM\ManyToOne(targetEntity: Agent::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private readonly Agent $agent;

    #[ORM\Column(length: 64)]
    private readonly string $secretHash;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private readonly \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private readonly ?\DateTimeImmutable $expiresAt;

    public function __construct(
        Agent $agent,
        string $secretHash,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $expiresAt,
    ) {
        $this->id = new Ulid();
        $this->agent = $agent;
        $this->secretHash = $secretHash;
        $this->createdAt = $createdAt;
        $this->expiresAt = $expiresAt;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function agent(): Agent
    {
        return $this->agent;
    }

    public function matchesSecretHash(string $secretHash): bool
    {
        return hash_equals($this->secretHash, $secretHash);
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function revokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function isRevoked(): bool
    {
        return null !== $this->revokedAt;
    }

    public function expiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpiredAt(\DateTimeImmutable $now): bool
    {
        return null !== $this->expiresAt && $this->expiresAt <= $now;
    }

    public function isActiveAt(\DateTimeImmutable $now): bool
    {
        return !$this->isRevoked() && !$this->isExpiredAt($now);
    }

    public function revoke(\DateTimeImmutable $revokedAt): void
    {
        $this->revokedAt ??= $revokedAt;
    }
}

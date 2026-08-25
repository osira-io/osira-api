<?php

declare(strict_types=1);

namespace App\Entity\Incident;

use App\Entity\Alert\AlertRule;
use App\Entity\Alert\AlertSeverity;
use App\Entity\Node\Node;
use App\Entity\User\User;
use App\Repository\Incident\IncidentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: IncidentRepository::class)]
#[ORM\Table(name: 'incidents')]
#[ORM\UniqueConstraint(name: 'uniq_incidents_active_identity', columns: ['active_identity'])]
#[ORM\Index(name: 'idx_incidents_status', columns: ['status'])]
#[ORM\Index(name: 'idx_incidents_severity', columns: ['severity'])]
#[ORM\Index(name: 'idx_incidents_first_triggered_at', columns: ['first_triggered_at'])]
final class Incident
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private readonly Ulid $id;

    /** @param array<string, string> $labels */
    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
        private readonly Node $node,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
        private readonly AlertRule $alertRule,
        #[ORM\Column(enumType: AlertSeverity::class, length: 16)] private readonly AlertSeverity $severity,
        #[ORM\Column(length: 255)] private readonly string $title,
        #[ORM\Column(type: Types::TEXT)] private readonly string $message,
        #[ORM\Column(type: Types::JSON)] private readonly array $labels,
        #[ORM\Column(length: 64, nullable: true)] private ?string $activeIdentity,
        #[ORM\Column(length: 255)] private string $lastValue,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)] private readonly \DateTimeImmutable $firstTriggeredAt,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)] private \DateTimeImmutable $lastTriggeredAt,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)] private readonly \DateTimeImmutable $createdAt,
    ) {
        $this->id = new Ulid();
        $this->status = IncidentStatus::FIRING;
        $this->occurrences = 1;
        $this->resolvedAt = null;
        $this->acknowledgedAt = null;
        $this->acknowledgedBy = null;
        $this->updatedAt = $createdAt;
    }

    #[ORM\Column(enumType: IncidentStatus::class, length: 16)]
    private IncidentStatus $status;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $resolvedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $acknowledgedAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?User $acknowledgedBy;

    #[ORM\Column]
    private int $occurrences;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function recordTrigger(string $lastValue, \DateTimeImmutable $now): void
    {
        if (IncidentStatus::FIRING !== $this->status) {
            throw new \LogicException('A resolved incident cannot be triggered again.');
        }
        $this->lastValue = $lastValue;
        $this->lastTriggeredAt = $now;
        $this->updatedAt = $now;
        ++$this->occurrences;
    }

    public function resolve(string $lastValue, \DateTimeImmutable $now): void
    {
        if (IncidentStatus::RESOLVED === $this->status) {
            return;
        }
        $this->status = IncidentStatus::RESOLVED;
        $this->lastValue = $lastValue;
        $this->resolvedAt = $now;
        $this->activeIdentity = null;
        $this->updatedAt = $now;
    }

    public function acknowledge(User $actor, \DateTimeImmutable $now): void
    {
        if (IncidentStatus::FIRING !== $this->status) {
            throw new \LogicException('Only a firing incident can be acknowledged.');
        }
        if (null !== $this->acknowledgedAt) {
            throw new \LogicException('The incident is already acknowledged.');
        }
        $this->acknowledgedAt = $now;
        $this->acknowledgedBy = $actor;
        $this->updatedAt = $now;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function node(): Node
    {
        return $this->node;
    }

    public function alertRule(): AlertRule
    {
        return $this->alertRule;
    }

    public function status(): IncidentStatus
    {
        return $this->status;
    }

    public function severity(): AlertSeverity
    {
        return $this->severity;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function message(): string
    {
        return $this->message;
    }

    /** @return array<string, string> */
    public function labels(): array
    {
        return $this->labels;
    }

    public function activeIdentity(): ?string
    {
        return $this->activeIdentity;
    }

    public function firstTriggeredAt(): \DateTimeImmutable
    {
        return $this->firstTriggeredAt;
    }

    public function lastTriggeredAt(): \DateTimeImmutable
    {
        return $this->lastTriggeredAt;
    }

    public function resolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function lastValue(): string
    {
        return $this->lastValue;
    }

    public function acknowledgedAt(): ?\DateTimeImmutable
    {
        return $this->acknowledgedAt;
    }

    public function acknowledgedBy(): ?User
    {
        return $this->acknowledgedBy;
    }

    public function occurrences(): int
    {
        return $this->occurrences;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}

<?php

declare(strict_types=1);

namespace App\Entity\Incident;

use App\Entity\User\User;
use App\Repository\Incident\IncidentActivityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: IncidentActivityRepository::class)]
#[ORM\Table(name: 'incident_activities')]
#[ORM\UniqueConstraint(name: 'uniq_incident_acknowledgement', columns: ['acknowledgement_key'])]
#[ORM\Index(name: 'idx_incident_activities_timeline', columns: ['incident_id', 'created_at', 'id'])]
final class IncidentActivity
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private readonly Ulid $id;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private readonly Incident $incident,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
        private readonly User $actor,
        #[ORM\Column(name: 'actor_ulid', length: 26)]
        private readonly string $actorId,
        #[ORM\Column(length: 180)]
        private readonly string $actorEmail,
        #[ORM\Column(enumType: IncidentActivityType::class, length: 24)]
        private readonly IncidentActivityType $type,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private readonly ?string $message,
        #[ORM\Column(length: 26, nullable: true)]
        private readonly ?string $acknowledgementKey,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private readonly \DateTimeImmutable $createdAt,
    ) {
        $this->id = new Ulid();
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function incident(): Incident
    {
        return $this->incident;
    }

    public function actor(): User
    {
        return $this->actor;
    }

    public function actorId(): string
    {
        return $this->actorId;
    }

    public function actorEmail(): string
    {
        return $this->actorEmail;
    }

    public function type(): IncidentActivityType
    {
        return $this->type;
    }

    public function message(): ?string
    {
        return $this->message;
    }

    public function acknowledgementKey(): ?string
    {
        return $this->acknowledgementKey;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}

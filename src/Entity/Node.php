<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NodeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: NodeRepository::class)]
#[ORM\Table(name: 'node')]
final class Node
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private readonly Ulid $id;

    #[ORM\Column(length: 255)]
    private readonly string $hostname;

    #[ORM\Column(length: 255, nullable: true)]
    private readonly ?string $displayName;

    #[ORM\Column(length: 64)]
    private readonly string $os;

    #[ORM\Column(length: 64)]
    private readonly string $architecture;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private readonly \DateTimeImmutable $firstSeenAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private readonly \DateTimeImmutable $createdAt;

    /** @var Collection<int, Agent> */
    #[ORM\OneToMany(targetEntity: Agent::class, mappedBy: 'node', cascade: ['persist'])]
    private Collection $agents;

    public function __construct(
        string $hostname,
        ?string $displayName,
        string $os,
        string $architecture,
        \DateTimeImmutable $firstSeenAt,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = new Ulid();
        $this->hostname = $hostname;
        $this->displayName = $displayName;
        $this->os = $os;
        $this->architecture = $architecture;
        $this->firstSeenAt = $firstSeenAt;
        $this->createdAt = $createdAt;
        $this->agents = new ArrayCollection();
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function hostname(): string
    {
        return $this->hostname;
    }

    public function displayName(): ?string
    {
        return $this->displayName;
    }

    public function os(): string
    {
        return $this->os;
    }

    public function architecture(): string
    {
        return $this->architecture;
    }

    public function firstSeenAt(): \DateTimeImmutable
    {
        return $this->firstSeenAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function addAgent(Agent $agent): void
    {
        if (!$this->agents->contains($agent)) {
            $this->agents->add($agent);
        }
    }
}

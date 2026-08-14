<?php

declare(strict_types=1);

namespace App\Node\Domain\Entity;

use App\Agent\Domain\Entity\Agent;
use App\Node\Infrastructure\Repository\NodeRepository;
use App\NodeGroup\Domain\Entity\NodeGroup;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: NodeRepository::class)]
#[ORM\Table(name: 'nodes')]
#[ORM\Index(columns: ['hostname'], name: 'idx_nodes_hostname')]
#[ORM\Index(columns: ['environment'], name: 'idx_nodes_environment')]
final class Node
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private readonly Ulid $id;

    #[ORM\Column(length: 255)]
    private readonly string $hostname;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $displayName;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $environment = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $tags = [];

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

    /** @var Collection<int, NodeGroup> */
    #[ORM\ManyToMany(targetEntity: NodeGroup::class)]
    #[ORM\JoinTable(name: 'node_group_nodes')]
    #[ORM\JoinColumn(name: 'node_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'node_group_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $groups;

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
        $this->groups = new ArrayCollection();
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

    public function environment(): ?string
    {
        return $this->environment;
    }

    /** @return list<string> */
    public function tags(): array
    {
        return $this->tags;
    }

    /** @return Collection<int, NodeGroup> */
    public function groups(): Collection
    {
        return $this->groups;
    }

    /** @param list<string> $tags */
    public function updateBusinessProperties(?string $displayName, ?string $environment, array $tags): void
    {
        $this->displayName = $displayName;
        $this->environment = $environment;
        $this->tags = $tags;
    }

    /** @param list<NodeGroup> $groups */
    public function replaceGroups(array $groups): void
    {
        $this->groups->clear();
        foreach ($groups as $group) {
            $this->groups->add($group);
        }
    }

    public function addAgent(Agent $agent): void
    {
        if (!$this->agents->contains($agent)) {
            $this->agents->add($agent);
        }
    }
}

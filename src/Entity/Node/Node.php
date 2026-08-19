<?php

declare(strict_types=1);

namespace App\Entity\Node;

use App\Entity\Agent\Agent;
use App\Entity\Monitoring\MonitoringTemplate;
use App\Entity\NodeGroup\NodeGroup;
use App\Repository\Node\NodeRepository;
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

    /** @var Collection<int, MonitoringTemplate> */
    #[ORM\ManyToMany(targetEntity: MonitoringTemplate::class, inversedBy: 'nodes')]
    #[ORM\JoinTable(name: 'node_monitoring_templates')]
    #[ORM\JoinColumn(name: 'node_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'monitoring_template_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $monitoringTemplates;

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
        $this->monitoringTemplates = new ArrayCollection();
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

    /** @return Collection<int, MonitoringTemplate> */
    public function monitoringTemplates(): Collection
    {
        return $this->monitoringTemplates;
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

    /** @param list<MonitoringTemplate> $monitoringTemplates */
    public function replaceMonitoringTemplates(array $monitoringTemplates): void
    {
        foreach ($this->monitoringTemplates->toArray() as $monitoringTemplate) {
            $this->removeMonitoringTemplate($monitoringTemplate);
        }
        foreach ($monitoringTemplates as $monitoringTemplate) {
            $this->addMonitoringTemplate($monitoringTemplate);
        }
    }

    public function addAgent(Agent $agent): void
    {
        if (!$this->agents->contains($agent)) {
            $this->agents->add($agent);
        }
    }

    private function addMonitoringTemplate(MonitoringTemplate $monitoringTemplate): void
    {
        if (!$this->monitoringTemplates->contains($monitoringTemplate)) {
            $this->monitoringTemplates->add($monitoringTemplate);
            $monitoringTemplate->addNode($this);
        }
    }

    private function removeMonitoringTemplate(MonitoringTemplate $monitoringTemplate): void
    {
        if ($this->monitoringTemplates->removeElement($monitoringTemplate)) {
            $monitoringTemplate->removeNode($this);
        }
    }
}

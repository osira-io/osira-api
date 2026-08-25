<?php

declare(strict_types=1);

namespace App\Entity\NodeGroup;

use App\Entity\Alert\AlertRule;
use App\Entity\Monitoring\MonitoringTemplate;
use App\Repository\NodeGroup\NodeGroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: NodeGroupRepository::class)]
#[ORM\Table(name: 'node_groups')]
#[ORM\UniqueConstraint(name: 'uniq_node_groups_name', columns: ['name'])]
final class NodeGroup
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private readonly Ulid $id;

    #[ORM\Column(length: 128)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private readonly \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, MonitoringTemplate> */
    #[ORM\ManyToMany(targetEntity: MonitoringTemplate::class, inversedBy: 'nodeGroups')]
    #[ORM\JoinTable(name: 'node_group_monitoring_templates')]
    #[ORM\JoinColumn(name: 'node_group_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'monitoring_template_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $monitoringTemplates;

    /** @var Collection<int, AlertRule> */
    #[ORM\ManyToMany(targetEntity: AlertRule::class, mappedBy: 'nodeGroups')]
    private Collection $alertRules;

    public function __construct(string $name, ?string $description, \DateTimeImmutable $now)
    {
        $this->id = new Ulid();
        $this->name = $name;
        $this->description = $description;
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->monitoringTemplates = new ArrayCollection();
        $this->alertRules = new ArrayCollection();
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, MonitoringTemplate> */
    public function monitoringTemplates(): Collection
    {
        return $this->monitoringTemplates;
    }

    /** @return Collection<int, AlertRule> */
    public function alertRules(): Collection
    {
        return $this->alertRules;
    }

    public function addAlertRule(AlertRule $alertRule): void
    {
        if (!$this->alertRules->contains($alertRule)) {
            $this->alertRules->add($alertRule);
        }
    }

    public function update(string $name, ?string $description, \DateTimeImmutable $now): void
    {
        $this->name = $name;
        $this->description = $description;
        $this->updatedAt = $now;
    }

    /** @param list<MonitoringTemplate> $monitoringTemplates */
    public function replaceMonitoringTemplates(array $monitoringTemplates, \DateTimeImmutable $now): void
    {
        foreach ($this->monitoringTemplates->toArray() as $monitoringTemplate) {
            $this->removeMonitoringTemplate($monitoringTemplate);
        }
        foreach ($monitoringTemplates as $monitoringTemplate) {
            $this->addMonitoringTemplate($monitoringTemplate);
        }
        $this->updatedAt = $now;
    }

    private function addMonitoringTemplate(MonitoringTemplate $monitoringTemplate): void
    {
        if (!$this->monitoringTemplates->contains($monitoringTemplate)) {
            $this->monitoringTemplates->add($monitoringTemplate);
            $monitoringTemplate->addNodeGroup($this);
        }
    }

    private function removeMonitoringTemplate(MonitoringTemplate $monitoringTemplate): void
    {
        if ($this->monitoringTemplates->removeElement($monitoringTemplate)) {
            $monitoringTemplate->removeNodeGroup($this);
        }
    }
}

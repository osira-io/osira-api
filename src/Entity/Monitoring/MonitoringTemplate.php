<?php

declare(strict_types=1);

namespace App\Entity\Monitoring;

use App\Entity\Alert\AlertRule;
use App\Entity\NodeGroup\NodeGroup;
use App\Repository\Monitoring\MonitoringTemplateRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: MonitoringTemplateRepository::class)]
#[ORM\Table(name: 'monitoring_templates')]
#[ORM\UniqueConstraint(name: 'uniq_monitoring_templates_name', columns: ['name'])]
#[ORM\UniqueConstraint(name: 'uniq_monitoring_templates_slug', columns: ['slug'])]
final class MonitoringTemplate
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private readonly Ulid $id;

    #[ORM\Column(length: 128)]
    private string $name;

    #[ORM\Column(length: 128)]
    private string $slug;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description;

    #[ORM\Column(options: ['default' => true])]
    private bool $isEnabled;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private readonly \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, ItemDefinition> */
    #[ORM\ManyToMany(targetEntity: ItemDefinition::class, inversedBy: 'monitoringTemplates')]
    #[ORM\JoinTable(name: 'monitoring_template_item_definitions')]
    #[ORM\JoinColumn(name: 'monitoring_template_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'item_definition_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $itemDefinitions;

    /** @var Collection<int, NodeGroup> */
    #[ORM\ManyToMany(targetEntity: NodeGroup::class, mappedBy: 'monitoringTemplates')]
    private Collection $nodeGroups;

    /** @var Collection<int, AlertRule> */
    #[ORM\ManyToMany(targetEntity: AlertRule::class, mappedBy: 'assignedTemplates')]
    private Collection $alertRules;

    public function __construct(
        string $name,
        string $slug,
        ?string $description,
        bool $isEnabled,
        \DateTimeImmutable $now,
    ) {
        $this->id = new Ulid();
        $this->name = $name;
        $this->slug = $slug;
        $this->description = $description;
        $this->isEnabled = $isEnabled;
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->itemDefinitions = new ArrayCollection();
        $this->nodeGroups = new ArrayCollection();
        $this->alertRules = new ArrayCollection();
    }

    public function update(string $name, string $slug, ?string $description, bool $isEnabled, \DateTimeImmutable $now): void
    {
        $this->name = $name;
        $this->slug = $slug;
        $this->description = $description;
        $this->isEnabled = $isEnabled;
        $this->updatedAt = $now;
    }

    /** @param iterable<ItemDefinition> $itemDefinitions */
    public function replaceItemDefinitions(iterable $itemDefinitions, \DateTimeImmutable $now): void
    {
        $this->itemDefinitions->clear();
        foreach ($itemDefinitions as $itemDefinition) {
            if (!$this->itemDefinitions->contains($itemDefinition)) {
                $this->itemDefinitions->add($itemDefinition);
            }
        }
        $this->updatedAt = $now;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, ItemDefinition> */
    public function itemDefinitions(): Collection
    {
        return $this->itemDefinitions;
    }

    /** @return Collection<int, NodeGroup> */
    public function nodeGroups(): Collection
    {
        return $this->nodeGroups;
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

    public function removeAlertRule(AlertRule $alertRule): void
    {
        $this->alertRules->removeElement($alertRule);
    }

    public function addNodeGroup(NodeGroup $nodeGroup): void
    {
        if (!$this->nodeGroups->contains($nodeGroup)) {
            $this->nodeGroups->add($nodeGroup);
        }
    }

    public function removeNodeGroup(NodeGroup $nodeGroup): void
    {
        $this->nodeGroups->removeElement($nodeGroup);
    }
}

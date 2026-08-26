<?php

declare(strict_types=1);

namespace App\Entity\Alert;

use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\MonitoringTemplate;
use App\Entity\Node\Node;
use App\Entity\NodeGroup\NodeGroup;
use App\Repository\Alert\AlertRuleRepository;
use App\Validator\Alert\AlertRuleAssignmentValidator;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: AlertRuleRepository::class)]
#[ORM\Table(name: 'alert_rules')]
final class AlertRule
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private readonly Ulid $id;

    #[ORM\Column(length: 128)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $message;

    #[ORM\Column(length: 255)]
    private string $expectedValue;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $recoveryThreshold;

    #[ORM\Column]
    private int $evaluationWindowSeconds;

    #[ORM\Column]
    private int $requiredOccurrences;

    #[ORM\Column(enumType: AlertOperator::class, length: 8)]
    private AlertOperator $operator;

    #[ORM\Column(enumType: AlertSeverity::class, length: 16)]
    private AlertSeverity $severity;

    #[ORM\Column(enumType: AlertRuleImpactType::class, length: 32)]
    private AlertRuleImpactType $impactType;

    #[ORM\Column(options: ['default' => true])]
    private bool $isEnabled;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private readonly \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, MonitoringTemplate> */
    #[ORM\ManyToMany(targetEntity: MonitoringTemplate::class, inversedBy: 'alertRules')]
    #[ORM\JoinTable(name: 'alert_rule_monitoring_templates')]
    private Collection $assignedTemplates;

    /** @var Collection<int, NodeGroup> */
    #[ORM\ManyToMany(targetEntity: NodeGroup::class, inversedBy: 'alertRules')]
    #[ORM\JoinTable(name: 'alert_rule_node_groups')]
    private Collection $nodeGroups;

    /** @var Collection<int, Node> */
    #[ORM\ManyToMany(targetEntity: Node::class, inversedBy: 'alertRules')]
    #[ORM\JoinTable(name: 'alert_rule_nodes')]
    private Collection $nodes;

    public function __construct(
        string $name,
        ?string $description,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
        private readonly ItemDefinition $itemDefinition,
        AlertOperator $operator,
        string $expectedValue,
        ?string $recoveryThreshold,
        int $evaluationWindowSeconds,
        int $requiredOccurrences,
        AlertSeverity $severity,
        AlertRuleImpactType $impactType,
        bool $isEnabled,
        \DateTimeImmutable $now,
    ) {
        self::assertPositive($evaluationWindowSeconds, $requiredOccurrences);
        $this->id = new Ulid();
        $this->name = $name;
        $this->description = $description;
        $this->title = $name;
        $this->message = $description ?? $name;
        $this->operator = $operator;
        $this->expectedValue = $expectedValue;
        $this->recoveryThreshold = $recoveryThreshold;
        $this->evaluationWindowSeconds = $evaluationWindowSeconds;
        $this->requiredOccurrences = $requiredOccurrences;
        $this->severity = $severity;
        $this->impactType = $impactType;
        $this->isEnabled = $isEnabled;
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->assignedTemplates = new ArrayCollection();
        $this->nodeGroups = new ArrayCollection();
        $this->nodes = new ArrayCollection();
    }

    public function update(string $name, ?string $description, AlertOperator $operator, string $expectedValue, ?string $recoveryThreshold, int $evaluationWindowSeconds, int $requiredOccurrences, AlertSeverity $severity, AlertRuleImpactType $impactType, bool $isEnabled, \DateTimeImmutable $now): void
    {
        self::assertPositive($evaluationWindowSeconds, $requiredOccurrences);
        $this->name = $name;
        $this->description = $description;
        $this->title = $name;
        $this->message = $description ?? $name;
        $this->operator = $operator;
        $this->expectedValue = $expectedValue;
        $this->recoveryThreshold = $recoveryThreshold;
        $this->evaluationWindowSeconds = $evaluationWindowSeconds;
        $this->requiredOccurrences = $requiredOccurrences;
        $this->severity = $severity;
        $this->impactType = $impactType;
        $this->isEnabled = $isEnabled;
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

    public function description(): ?string
    {
        return $this->description;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function itemDefinition(): ItemDefinition
    {
        return $this->itemDefinition;
    }

    public function operator(): AlertOperator
    {
        return $this->operator;
    }

    public function expectedValue(): string
    {
        return $this->expectedValue;
    }

    public function recoveryThreshold(): ?string
    {
        return $this->recoveryThreshold;
    }

    public function evaluationWindowSeconds(): int
    {
        return $this->evaluationWindowSeconds;
    }

    public function requiredOccurrences(): int
    {
        return $this->requiredOccurrences;
    }

    public function severity(): AlertSeverity
    {
        return $this->severity;
    }

    public function impactType(): AlertRuleImpactType
    {
        return $this->impactType;
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

    /** @return Collection<int, MonitoringTemplate> */
    public function assignedTemplates(): Collection
    {
        return $this->assignedTemplates;
    }

    /** @return Collection<int, NodeGroup> */
    public function nodeGroups(): Collection
    {
        return $this->nodeGroups;
    }

    /** @return Collection<int, Node> */
    public function nodes(): Collection
    {
        return $this->nodes;
    }

    public function assignToTemplate(MonitoringTemplate $template): void
    {
        AlertRuleAssignmentValidator::assertTemplateItem($this->itemDefinition, $template);
        if (!$this->assignedTemplates->contains($template)) {
            $this->assignedTemplates->add($template);
            $template->addAlertRule($this);
        }
    }

    public function assignToNodeGroup(NodeGroup $group): void
    {
        AlertRuleAssignmentValidator::assertNodeGroupItem($this->itemDefinition, $group);
        if (!$this->nodeGroups->contains($group)) {
            $this->nodeGroups->add($group);
            $group->addAlertRule($this);
        }
    }

    public function assignToNode(Node $node): void
    {
        AlertRuleAssignmentValidator::assertNodeItem($this->itemDefinition, $node);
        if (!$this->nodes->contains($node)) {
            $this->nodes->add($node);
            $node->addAlertRule($this);
        }
    }

    /** @param list<MonitoringTemplate> $templates */
    public function replaceTemplates(array $templates, \DateTimeImmutable $now): void
    {
        foreach ($this->assignedTemplates->toArray() as $existing) {
            if (!\in_array($existing, $templates, true)) {
                $this->assignedTemplates->removeElement($existing);
                $existing->removeAlertRule($this);
            }
        }
        foreach ($templates as $template) {
            $this->assignToTemplate($template);
        }
        $this->updatedAt = $now;
    }

    /** @param list<NodeGroup> $groups */
    public function replaceNodeGroups(array $groups, \DateTimeImmutable $now): void
    {
        foreach ($this->nodeGroups->toArray() as $existing) {
            if (!\in_array($existing, $groups, true)) {
                $this->nodeGroups->removeElement($existing);
                $existing->removeAlertRule($this);
            }
        }
        foreach ($groups as $group) {
            $this->assignToNodeGroup($group);
        }
        $this->updatedAt = $now;
    }

    /** @param list<Node> $nodes */
    public function replaceNodes(array $nodes, \DateTimeImmutable $now): void
    {
        foreach ($this->nodes->toArray() as $existing) {
            if (!\in_array($existing, $nodes, true)) {
                $this->nodes->removeElement($existing);
                $existing->removeAlertRule($this);
            }
        }
        foreach ($nodes as $node) {
            $this->assignToNode($node);
        }
        $this->updatedAt = $now;
    }

    private static function assertPositive(int $evaluationWindowSeconds, int $requiredOccurrences): void
    {
        if ($evaluationWindowSeconds < 1 || $requiredOccurrences < 1) {
            throw new \InvalidArgumentException('Alert evaluation window and required occurrences must be positive.');
        }
    }
}

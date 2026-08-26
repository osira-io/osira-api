<?php

declare(strict_types=1);

namespace App\Entity\Sla;

use App\Entity\Node\Node;
use App\Entity\NodeGroup\NodeGroup;
use App\Repository\Sla\SlaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: SlaRepository::class)]
#[ORM\Table(name: 'slas')]
#[ORM\UniqueConstraint(name: 'uniq_slas_name', columns: ['name'])]
final class Sla
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private readonly Ulid $id;

    #[ORM\Column(length: 128)] private string $name;
    #[ORM\Column(type: Types::TEXT, nullable: true)] private ?string $description;
    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 3)] private string $targetPercentage;
    #[ORM\Column(enumType: SlaPeriodType::class, length: 32)] private SlaPeriodType $periodType;
    #[ORM\Column(options: ['default' => true])] private bool $excludeMaintenance;
    #[ORM\Column(options: ['default' => true])] private bool $isEnabled;
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)] private readonly \DateTimeImmutable $createdAt;
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)] private \DateTimeImmutable $updatedAt;
    /** @var Collection<int, Node> */
    #[ORM\ManyToMany(targetEntity: Node::class)]
    #[ORM\JoinTable(name: 'sla_nodes')]
    #[ORM\JoinColumn(name: 'sla_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'node_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $nodes;

    /** @var Collection<int, NodeGroup> */
    #[ORM\ManyToMany(targetEntity: NodeGroup::class)]
    #[ORM\JoinTable(name: 'sla_node_groups')]
    #[ORM\JoinColumn(name: 'sla_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'node_group_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $nodeGroups;

    public function __construct(string $name, ?string $description, float $targetPercentage, SlaPeriodType $periodType, bool $excludeMaintenance, bool $isEnabled, \DateTimeImmutable $now)
    {
        self::assertTarget($targetPercentage);
        $this->id = new Ulid();
        $this->name = $name;
        $this->description = $description;
        $this->targetPercentage = number_format($targetPercentage, 3, '.', '');
        $this->periodType = $periodType;
        $this->excludeMaintenance = $excludeMaintenance;
        $this->isEnabled = $isEnabled;
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->nodes = new ArrayCollection();
        $this->nodeGroups = new ArrayCollection();
    }

    public function update(string $name, ?string $description, float $targetPercentage, SlaPeriodType $periodType, bool $excludeMaintenance, bool $isEnabled, \DateTimeImmutable $now): void
    {
        self::assertTarget($targetPercentage);
        $this->name = $name;
        $this->description = $description;
        $this->targetPercentage = number_format($targetPercentage, 3, '.', '');
        $this->periodType = $periodType;
        $this->excludeMaintenance = $excludeMaintenance;
        $this->isEnabled = $isEnabled;
        $this->updatedAt = $now;
    }

    /** @param list<Node> $nodes */
    public function replaceNodes(array $nodes, \DateTimeImmutable $now): void
    {
        $this->nodes->clear();
        foreach ($nodes as $node) {
            if (!$this->nodes->contains($node)) {
                $this->nodes->add($node);
            }
        }
        $this->updatedAt = $now;
    }

    /** @param list<NodeGroup> $groups */
    public function replaceNodeGroups(array $groups, \DateTimeImmutable $now): void
    {
        $this->nodeGroups->clear();
        foreach ($groups as $group) {
            if (!$this->nodeGroups->contains($group)) {
                $this->nodeGroups->add($group);
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

    public function description(): ?string
    {
        return $this->description;
    }

    public function targetPercentage(): float
    {
        return (float) $this->targetPercentage;
    }

    public function periodType(): SlaPeriodType
    {
        return $this->periodType;
    }

    public function excludeMaintenance(): bool
    {
        return $this->excludeMaintenance;
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

    /** @return Collection<int, Node> */
    public function nodes(): Collection
    {
        return $this->nodes;
    }

    /** @return Collection<int, NodeGroup> */
    public function nodeGroups(): Collection
    {
        return $this->nodeGroups;
    }

    private static function assertTarget(float $target): void
    {
        if (!is_finite($target) || $target < 0.0 || $target > 100.0) {
            throw new \InvalidArgumentException('SLA target percentage must be between 0 and 100.');
        }
    }
}

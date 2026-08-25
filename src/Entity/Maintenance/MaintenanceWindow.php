<?php

declare(strict_types=1);

namespace App\Entity\Maintenance;

use App\Entity\Node\Node;
use App\Entity\NodeGroup\NodeGroup;
use App\Repository\Maintenance\MaintenanceWindowRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: MaintenanceWindowRepository::class)]
#[ORM\Table(name: 'maintenance_windows')]
#[ORM\Index(columns: ['is_enabled', 'starts_at', 'ends_at'], name: 'idx_maintenance_windows_active_period')]
final class MaintenanceWindow
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private readonly Ulid $id;

    #[ORM\Column(length: 128)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $endsAt;

    #[ORM\Column(options: ['default' => true])]
    private bool $isEnabled;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private readonly \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, Node> */
    #[ORM\ManyToMany(targetEntity: Node::class)]
    #[ORM\JoinTable(name: 'maintenance_window_nodes')]
    #[ORM\JoinColumn(name: 'maintenance_window_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'node_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $nodes;

    /** @var Collection<int, NodeGroup> */
    #[ORM\ManyToMany(targetEntity: NodeGroup::class)]
    #[ORM\JoinTable(name: 'maintenance_window_node_groups')]
    #[ORM\JoinColumn(name: 'maintenance_window_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'node_group_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $nodeGroups;

    public function __construct(
        string $name,
        ?string $description,
        \DateTimeImmutable $startsAt,
        \DateTimeImmutable $endsAt,
        bool $isEnabled,
        \DateTimeImmutable $now,
    ) {
        self::assertValidInterval($startsAt, $endsAt);
        $this->id = new Ulid();
        $this->name = $name;
        $this->description = $description;
        $this->startsAt = self::utc($startsAt);
        $this->endsAt = self::utc($endsAt);
        $this->isEnabled = $isEnabled;
        $this->createdAt = self::utc($now);
        $this->updatedAt = self::utc($now);
        $this->nodes = new ArrayCollection();
        $this->nodeGroups = new ArrayCollection();
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

    public function startsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function endsAt(): \DateTimeImmutable
    {
        return $this->endsAt;
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

    public function update(
        string $name,
        ?string $description,
        \DateTimeImmutable $startsAt,
        \DateTimeImmutable $endsAt,
        bool $isEnabled,
        \DateTimeImmutable $now,
    ): void {
        self::assertValidInterval($startsAt, $endsAt);
        $this->name = $name;
        $this->description = $description;
        $this->startsAt = self::utc($startsAt);
        $this->endsAt = self::utc($endsAt);
        $this->isEnabled = $isEnabled;
        $this->updatedAt = self::utc($now);
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
        $this->updatedAt = self::utc($now);
    }

    /** @param list<NodeGroup> $nodeGroups */
    public function replaceNodeGroups(array $nodeGroups, \DateTimeImmutable $now): void
    {
        $this->nodeGroups->clear();
        foreach ($nodeGroups as $nodeGroup) {
            if (!$this->nodeGroups->contains($nodeGroup)) {
                $this->nodeGroups->add($nodeGroup);
            }
        }
        $this->updatedAt = self::utc($now);
    }

    public function isActiveAt(\DateTimeImmutable $now): bool
    {
        $now = self::utc($now);

        return $this->isEnabled && $this->startsAt <= $now && $this->endsAt > $now;
    }

    public function targetsNode(Node $node): bool
    {
        if ($this->nodes->contains($node)) {
            return true;
        }
        foreach ($node->groups() as $group) {
            if ($this->nodeGroups->contains($group)) {
                return true;
            }
        }

        return false;
    }

    private static function assertValidInterval(\DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt): void
    {
        if ($endsAt <= $startsAt) {
            throw new \InvalidArgumentException('Maintenance window end must be after start.');
        }
    }

    private static function utc(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->setTimezone(new \DateTimeZone('UTC'));
    }
}

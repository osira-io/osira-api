<?php

declare(strict_types=1);

namespace App\Entity\Notification;

use App\Entity\Alert\AlertSeverity;
use App\Entity\Node\Node;
use App\Entity\NodeGroup\NodeGroup;
use App\Repository\Notification\NotificationRuleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: NotificationRuleRepository::class)]
#[ORM\Table(name: 'notification_rules')]
#[ORM\UniqueConstraint(name: 'uniq_notification_rules_name', columns: ['name'])]
final class NotificationRule
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private readonly Ulid $id;

    /** @var Collection<int, NotificationChannel> */
    #[ORM\ManyToMany(targetEntity: NotificationChannel::class)]
    #[ORM\JoinTable(name: 'notification_rule_channels')]
    private Collection $channels;

    /** @var Collection<int, Node> */
    #[ORM\ManyToMany(targetEntity: Node::class)]
    #[ORM\JoinTable(name: 'notification_rule_nodes')]
    private Collection $nodes;

    /** @var Collection<int, NodeGroup> */
    #[ORM\ManyToMany(targetEntity: NodeGroup::class)]
    #[ORM\JoinTable(name: 'notification_rule_node_groups')]
    private Collection $nodeGroups;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $severities;

    /** @param list<AlertSeverity> $severities */
    public function __construct(
        #[ORM\Column(length: 128)] private string $name,
        #[ORM\Column(options: ['default' => true])] private bool $isEnabled,
        array $severities,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)] private readonly \DateTimeImmutable $createdAt,
    ) {
        $this->id = new Ulid();
        $this->severities = array_map(static fn (AlertSeverity $severity): string => $severity->value, $severities);
        $this->updatedAt = $createdAt;
        $this->channels = new ArrayCollection();
        $this->nodes = new ArrayCollection();
        $this->nodeGroups = new ArrayCollection();
    }

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function id(): Ulid
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    /** @return list<AlertSeverity> */
    public function severities(): array
    {
        return array_map(AlertSeverity::from(...), $this->severities);
    }

    /** @return Collection<int, NotificationChannel> */
    public function channels(): Collection
    {
        return $this->channels;
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

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @param list<AlertSeverity> $severities */
    public function update(string $name, bool $isEnabled, array $severities, \DateTimeImmutable $now): void
    {
        $this->name = $name;
        $this->isEnabled = $isEnabled;
        $this->severities = array_map(static fn (AlertSeverity $severity): string => $severity->value, $severities);
        $this->updatedAt = $now;
    }

    /** @param list<NotificationChannel> $channels */
    public function replaceChannels(array $channels, \DateTimeImmutable $now): void
    {
        $this->replace($this->channels, $channels);
        $this->updatedAt = $now;
    }

    /** @param list<Node> $nodes */
    public function replaceNodes(array $nodes, \DateTimeImmutable $now): void
    {
        $this->replace($this->nodes, $nodes);
        $this->updatedAt = $now;
    }

    /** @param list<NodeGroup> $groups */
    public function replaceNodeGroups(array $groups, \DateTimeImmutable $now): void
    {
        $this->replace($this->nodeGroups, $groups);
        $this->updatedAt = $now;
    }

    public function matches(Node $node, AlertSeverity $severity): bool
    {
        if (!$this->isEnabled || !\in_array($severity->value, $this->severities, true)) {
            return false;
        }
        if ($this->nodes->isEmpty() && $this->nodeGroups->isEmpty()) {
            return true;
        }
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

    /** @template T of object
     * @param Collection<int, T> $collection
     * @param list<T> $values
     */
    private function replace(Collection $collection, array $values): void
    {
        $collection->clear();
        foreach ($values as $value) {
            if (!$collection->contains($value)) {
                $collection->add($value);
            }
        }
    }
}

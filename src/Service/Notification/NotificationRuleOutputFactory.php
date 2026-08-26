<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Dto\Node\NodeSummary;
use App\Dto\NodeGroup\NodeGroupSummary;
use App\Dto\Notification\NotificationRuleOutput;
use App\Entity\Notification\NotificationRule;

final readonly class NotificationRuleOutputFactory
{
    public function __construct(private NotificationChannelOutputFactory $channels)
    {
    }

    public function create(NotificationRule $rule): NotificationRuleOutput
    {
        $channels = array_values(array_map($this->channels->create(...), $rule->channels()->toArray()));
        $nodes = array_values(array_map(static fn ($node): NodeSummary => new NodeSummary((string) $node->id(), $node->hostname(), $node->displayName()), $rule->nodes()->toArray()));
        $groups = array_values(array_map(static fn ($group): NodeGroupSummary => new NodeGroupSummary((string) $group->id(), $group->name()), $rule->nodeGroups()->toArray()));

        return new NotificationRuleOutput((string) $rule->id(), $rule->name(), $rule->isEnabled(), array_map(static fn ($severity): string => $severity->value, $rule->severities()), $channels, $nodes, $groups, $rule->createdAt(), $rule->updatedAt());
    }
}

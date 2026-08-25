<?php

declare(strict_types=1);

namespace App\Validator\Alert;

use App\Entity\Alert\AlertRule;
use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\MonitoringTemplate;
use App\Entity\Node\Node;
use App\Entity\NodeGroup\NodeGroup;

final class AlertRuleAssignmentValidator
{
    public static function assertTemplateItem(ItemDefinition $item, MonitoringTemplate $template): void
    {
        if (!$template->itemDefinitions()->contains($item)) {
            throw new \InvalidArgumentException('A template alert rule must target an item provided by that template.');
        }
    }

    public static function assertNodeGroupItem(ItemDefinition $item, NodeGroup $group): void
    {
        foreach ($group->monitoringTemplates() as $template) {
            if ($template->isEnabled() && $template->itemDefinitions()->contains($item)) {
                return;
            }
        }

        throw new \InvalidArgumentException('A node-group alert rule must target an item provided by one of its templates.');
    }

    public static function assertNodeItem(ItemDefinition $item, Node $node): void
    {
        foreach ($node->groups() as $group) {
            foreach ($group->monitoringTemplates() as $template) {
                if ($template->isEnabled() && $template->itemDefinitions()->contains($item)) {
                    if (null !== $item->commandForOs($node->os()) && $item->valueType()->isMetricCompatible()) {
                        return;
                    }

                    break 2;
                }
            }
        }

        throw new \InvalidArgumentException('A node alert rule must target an effective OS-compatible metric item.');
    }

    /** @param array<string, true> $effectiveItemIds */
    public static function isEffectiveForNode(AlertRule $rule, Node $node, array $effectiveItemIds): bool
    {
        $item = $rule->itemDefinition();

        return $rule->isEnabled()
            && isset($effectiveItemIds[(string) $item->id()])
            && $item->valueType()->isMetricCompatible()
            && null !== $item->commandForOs($node->os());
    }
}

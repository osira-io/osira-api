<?php

declare(strict_types=1);

namespace App\Service\Monitoring;

use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\MonitoringTemplate;
use App\Entity\Node\Node;

final class EffectiveNodeMonitoringResolver
{
    public function resolve(Node $node): EffectiveNodeMonitoring
    {
        $templatesById = [];

        foreach ($node->monitoringTemplates() as $template) {
            if ($template->isEnabled()) {
                $templatesById[(string) $template->id()] = $template;
            }
        }

        foreach ($node->groups() as $group) {
            foreach ($group->monitoringTemplates() as $template) {
                if ($template->isEnabled()) {
                    $templatesById[(string) $template->id()] = $template;
                }
            }
        }

        $templates = array_values($templatesById);
        usort($templates, static fn (MonitoringTemplate $left, MonitoringTemplate $right): int => [$left->name(), (string) $left->id()] <=> [$right->name(), (string) $right->id()]);

        $itemsById = [];
        foreach ($templates as $template) {
            foreach ($template->itemDefinitions() as $itemDefinition) {
                if ($itemDefinition->isEnabled()) {
                    $itemsById[(string) $itemDefinition->id()] = $itemDefinition;
                }
            }
        }

        $items = array_values($itemsById);
        usort($items, static fn (ItemDefinition $left, ItemDefinition $right): int => [$left->key(), (string) $left->id()] <=> [$right->key(), (string) $right->id()]);

        return new EffectiveNodeMonitoring($templates, $items);
    }
}

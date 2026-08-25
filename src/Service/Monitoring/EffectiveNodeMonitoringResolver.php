<?php

declare(strict_types=1);

namespace App\Service\Monitoring;

use App\Entity\Alert\AlertRule;
use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\MonitoringTemplate;
use App\Entity\Node\Node;
use App\Validator\Alert\AlertRuleAssignmentValidator;

final class EffectiveNodeMonitoringResolver
{
    public function resolve(Node $node): EffectiveNodeMonitoring
    {
        [$templates, $items] = $this->resolveTemplatesAndItems($node);

        return new EffectiveNodeMonitoring($templates, $items, $this->getEffectiveAlertRules($node, $templates, $items));
    }

    /** @param list<MonitoringTemplate>|null $templates
     * @param list<ItemDefinition>|null $items
     *
     * @return list<AlertRule>
     */
    public function getEffectiveAlertRules(Node $node, ?array $templates = null, ?array $items = null): array
    {
        if (null === $templates || null === $items) {
            [$templates, $items] = $this->resolveTemplatesAndItems($node);
        }

        $effectiveItemIds = array_fill_keys(array_map(static fn (ItemDefinition $item): string => (string) $item->id(), $items), true);
        $rulesById = [];
        foreach ($node->alertRules() as $rule) {
            $rulesById[(string) $rule->id()] = $rule;
        }
        foreach ($node->groups() as $group) {
            foreach ($group->alertRules() as $rule) {
                $rulesById[(string) $rule->id()] = $rule;
            }
        }
        foreach ($templates as $template) {
            foreach ($template->alertRules() as $rule) {
                $rulesById[(string) $rule->id()] = $rule;
            }
        }

        $rules = array_values(array_filter($rulesById, static fn (AlertRule $rule): bool => AlertRuleAssignmentValidator::isEffectiveForNode($rule, $node, $effectiveItemIds)));
        usort($rules, static fn (AlertRule $left, AlertRule $right): int => [$left->name(), (string) $left->id()] <=> [$right->name(), (string) $right->id()]);

        return $rules;
    }

    /** @return array{list<MonitoringTemplate>, list<ItemDefinition>} */
    private function resolveTemplatesAndItems(Node $node): array
    {
        $templatesById = [];
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
                if ($itemDefinition->isEnabled() && $itemDefinition->valueType()->isMetricCompatible() && null !== $itemDefinition->commandForOs($node->os())) {
                    $itemsById[(string) $itemDefinition->id()] = $itemDefinition;
                }
            }
        }
        $items = array_values($itemsById);
        usort($items, static fn (ItemDefinition $left, ItemDefinition $right): int => [$left->key(), (string) $left->id()] <=> [$right->key(), (string) $right->id()]);

        return [$templates, $items];
    }
}

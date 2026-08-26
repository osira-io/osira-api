<?php

declare(strict_types=1);

namespace App\Service\Alert;

use App\Dto\Alert\AlertRuleOutput;
use App\Entity\Alert\AlertRule;
use App\Service\Monitoring\ItemDefinitionOutputFactory;

final readonly class AlertRuleOutputFactory
{
    public function __construct(private ItemDefinitionOutputFactory $itemDefinitionOutputFactory)
    {
    }

    public function create(AlertRule $alertRule): AlertRuleOutput
    {
        $monitoringTemplateIds = [];
        foreach ($alertRule->assignedTemplates() as $template) {
            $monitoringTemplateIds[] = (string) $template->id();
        }
        sort($monitoringTemplateIds);

        $nodeGroupIds = [];
        foreach ($alertRule->nodeGroups() as $group) {
            $nodeGroupIds[] = (string) $group->id();
        }
        sort($nodeGroupIds);

        $nodeIds = [];
        foreach ($alertRule->nodes() as $node) {
            $nodeIds[] = (string) $node->id();
        }
        sort($nodeIds);

        return new AlertRuleOutput(
            (string) $alertRule->id(),
            $alertRule->name(),
            $alertRule->description(),
            $this->itemDefinitionOutputFactory->createSummary($alertRule->itemDefinition()),
            $alertRule->operator()->value,
            $alertRule->expectedValue(),
            $alertRule->recoveryThreshold(),
            $alertRule->severity()->value,
            $alertRule->impactType()->value,
            $alertRule->evaluationWindowSeconds(),
            $alertRule->requiredOccurrences(),
            $alertRule->isEnabled(),
            $monitoringTemplateIds,
            $nodeGroupIds,
            $nodeIds,
            $alertRule->createdAt(),
            $alertRule->updatedAt(),
        );
    }
}

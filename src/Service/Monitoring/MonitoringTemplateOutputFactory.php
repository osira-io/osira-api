<?php

declare(strict_types=1);

namespace App\Service\Monitoring;

use App\Dto\Monitoring\MonitoringTemplateOutput;
use App\Dto\Monitoring\MonitoringTemplateSummary;
use App\Dto\NodeGroup\NodeGroupSummary;
use App\Entity\Monitoring\MonitoringTemplate;

final readonly class MonitoringTemplateOutputFactory
{
    public function __construct(private ItemDefinitionOutputFactory $itemDefinitionOutputFactory)
    {
    }

    public function create(MonitoringTemplate $monitoringTemplate): MonitoringTemplateOutput
    {
        $itemDefinitions = [];
        foreach ($monitoringTemplate->itemDefinitions() as $itemDefinition) {
            $itemDefinitions[] = $this->itemDefinitionOutputFactory->createSummary($itemDefinition);
        }

        $nodeGroups = [];
        foreach ($monitoringTemplate->nodeGroups() as $nodeGroup) {
            $nodeGroups[] = new NodeGroupSummary((string) $nodeGroup->id(), $nodeGroup->name());
        }

        return new MonitoringTemplateOutput(
            (string) $monitoringTemplate->id(),
            $monitoringTemplate->name(),
            $monitoringTemplate->slug(),
            $monitoringTemplate->description(),
            $monitoringTemplate->isEnabled(),
            $itemDefinitions,
            $nodeGroups,
            $monitoringTemplate->createdAt(),
            $monitoringTemplate->updatedAt(),
        );
    }

    public function createSummary(MonitoringTemplate $monitoringTemplate): MonitoringTemplateSummary
    {
        return new MonitoringTemplateSummary(
            (string) $monitoringTemplate->id(),
            $monitoringTemplate->name(),
            $monitoringTemplate->slug(),
            $monitoringTemplate->isEnabled(),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Service\NodeGroup;

use App\Dto\Monitoring\MonitoringTemplateSummary;
use App\Dto\NodeGroup\NodeGroupOutput;
use App\Entity\NodeGroup\NodeGroup;

final readonly class NodeGroupOutputFactory
{
    public function create(NodeGroup $group): NodeGroupOutput
    {
        $monitoringTemplates = [];
        foreach ($group->monitoringTemplates() as $monitoringTemplate) {
            $monitoringTemplates[] = new MonitoringTemplateSummary(
                (string) $monitoringTemplate->id(),
                $monitoringTemplate->name(),
                $monitoringTemplate->slug(),
                $monitoringTemplate->isEnabled(),
            );
        }

        return new NodeGroupOutput(
            (string) $group->id(),
            $group->name(),
            $group->description(),
            $monitoringTemplates,
            $group->createdAt(),
            $group->updatedAt(),
        );
    }
}

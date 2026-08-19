<?php

declare(strict_types=1);

namespace App\Service\Node;

use App\Dto\Monitoring\MonitoringTemplateSummary;
use App\Dto\Node\NodeOutput;
use App\Dto\NodeGroup\NodeGroupSummary;
use App\Entity\Node\Node;

final readonly class NodeOutputFactory
{
    public function create(Node $node): NodeOutput
    {
        $groups = [];
        foreach ($node->groups() as $group) {
            $groups[] = new NodeGroupSummary((string) $group->id(), $group->name());
        }

        $monitoringTemplates = [];
        foreach ($node->monitoringTemplates() as $monitoringTemplate) {
            $monitoringTemplates[] = new MonitoringTemplateSummary(
                (string) $monitoringTemplate->id(),
                $monitoringTemplate->name(),
                $monitoringTemplate->slug(),
                $monitoringTemplate->isEnabled(),
            );
        }

        return new NodeOutput(
            (string) $node->id(),
            $node->hostname(),
            $node->displayName(),
            $node->os(),
            $node->architecture(),
            $node->environment(),
            $node->tags(),
            $groups,
            $monitoringTemplates,
            $node->firstSeenAt(),
            $node->createdAt(),
        );
    }
}

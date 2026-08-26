<?php

declare(strict_types=1);

namespace App\Service\Sla;

use App\Dto\Node\NodeSummary;
use App\Dto\NodeGroup\NodeGroupSummary;
use App\Dto\Sla\SlaOutput;
use App\Entity\Sla\Sla;

final readonly class SlaOutputFactory
{
    public function create(Sla $sla): SlaOutput
    {
        $nodes = [];
        foreach ($sla->nodes() as $node) {
            $nodes[] = new NodeSummary((string) $node->id(), $node->hostname(), $node->displayName());
        }
        usort($nodes, static fn (NodeSummary $a, NodeSummary $b): int => [$a->hostname, $a->id] <=> [$b->hostname, $b->id]);
        $groups = [];
        foreach ($sla->nodeGroups() as $group) {
            $groups[] = new NodeGroupSummary((string) $group->id(), $group->name());
        }
        usort($groups, static fn (NodeGroupSummary $a, NodeGroupSummary $b): int => [$a->name, $a->id] <=> [$b->name, $b->id]);

        return new SlaOutput((string) $sla->id(), $sla->name(), $sla->description(), $sla->targetPercentage(), $sla->periodType()->value, $sla->excludeMaintenance(), $sla->isEnabled(), $nodes, $groups, $sla->createdAt(), $sla->updatedAt());
    }
}

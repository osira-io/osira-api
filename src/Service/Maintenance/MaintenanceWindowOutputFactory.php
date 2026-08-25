<?php

declare(strict_types=1);

namespace App\Service\Maintenance;

use App\Dto\Maintenance\MaintenanceWindowOutput;
use App\Dto\Node\NodeSummary;
use App\Dto\NodeGroup\NodeGroupSummary;
use App\Entity\Maintenance\MaintenanceWindow;

final readonly class MaintenanceWindowOutputFactory
{
    public function create(MaintenanceWindow $window): MaintenanceWindowOutput
    {
        $nodes = [];
        foreach ($window->nodes() as $node) {
            $nodes[] = new NodeSummary((string) $node->id(), $node->hostname(), $node->displayName());
        }
        usort($nodes, static fn (NodeSummary $left, NodeSummary $right): int => [$left->hostname, $left->id] <=> [$right->hostname, $right->id]);

        $groups = [];
        foreach ($window->nodeGroups() as $group) {
            $groups[] = new NodeGroupSummary((string) $group->id(), $group->name());
        }
        usort($groups, static fn (NodeGroupSummary $left, NodeGroupSummary $right): int => [$left->name, $left->id] <=> [$right->name, $right->id]);

        return new MaintenanceWindowOutput(
            (string) $window->id(),
            $window->name(),
            $window->description(),
            $window->startsAt(),
            $window->endsAt(),
            $window->isEnabled(),
            $nodes,
            $groups,
            $window->createdAt(),
            $window->updatedAt(),
        );
    }
}

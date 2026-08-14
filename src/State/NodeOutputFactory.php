<?php

declare(strict_types=1);

namespace App\State;

use App\ApiResource\NodeGroupSummary;
use App\ApiResource\NodeOutput;
use App\Entity\Node;

final readonly class NodeOutputFactory
{
    public function create(Node $node): NodeOutput
    {
        $groups = [];
        foreach ($node->groups() as $group) {
            $groups[] = new NodeGroupSummary((string) $group->id(), $group->name());
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
            $node->firstSeenAt(),
            $node->createdAt(),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Node\Presentation\Api\Factory;

use App\Node\Domain\Entity\Node;
use App\Node\Presentation\Api\Resource\NodeOutput;
use App\NodeGroup\Presentation\Api\Resource\NodeGroupSummary;

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

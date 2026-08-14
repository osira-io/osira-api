<?php

declare(strict_types=1);

namespace App\Service\NodeGroup;

use App\Dto\NodeGroup\NodeGroupOutput;
use App\Entity\NodeGroup\NodeGroup;

final readonly class NodeGroupOutputFactory
{
    public function create(NodeGroup $group): NodeGroupOutput
    {
        return new NodeGroupOutput(
            (string) $group->id(),
            $group->name(),
            $group->description(),
            $group->createdAt(),
            $group->updatedAt(),
        );
    }
}

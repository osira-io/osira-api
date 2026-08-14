<?php

declare(strict_types=1);

namespace App\NodeGroup\Presentation\Api\Factory;

use App\NodeGroup\Domain\Entity\NodeGroup;
use App\NodeGroup\Presentation\Api\Resource\NodeGroupOutput;

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

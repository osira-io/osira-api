<?php

declare(strict_types=1);

namespace App\NodeGroup\Presentation\Api\Resource;

final readonly class NodeGroupSummary
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }
}

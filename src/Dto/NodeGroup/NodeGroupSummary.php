<?php

declare(strict_types=1);

namespace App\Dto\NodeGroup;

final readonly class NodeGroupSummary
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }
}

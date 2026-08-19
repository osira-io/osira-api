<?php

declare(strict_types=1);

namespace App\Dto\Node;

final readonly class NodeSummary
{
    public function __construct(
        public string $id,
        public string $hostname,
        public ?string $displayName,
    ) {
    }
}
